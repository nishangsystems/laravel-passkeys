<?php

namespace NishangSystems\Passkeys\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use NishangSystems\Passkeys\Exceptions\PasskeyException;
use NishangSystems\Passkeys\Http\Requests\GetPasskeyLoginOptionsRequest;
use NishangSystems\Passkeys\Http\Requests\GetPasskeyRegisterOptionsRequest;
use NishangSystems\Passkeys\Http\Requests\RegisterPasskeyRequest;
use NishangSystems\Passkeys\Http\Requests\VerifyPasskeyRequest;
use NishangSystems\Passkeys\Http\Resources\PasskeyResource;
use NishangSystems\Passkeys\Models\Passkey;
use NishangSystems\Passkeys\Services\PasskeyService;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Webauthn\Exception\InvalidDataException;

class PasskeyController
{
    use RespondsWithJson;

    private PasskeyService $passkeyService;

    public function __construct(PasskeyService $passkeyService)
    {
        $this->passkeyService = $passkeyService;
    }

    /**
     * @throws ExceptionInterface
     * @throws InvalidDataException
     */
    public function registerOptions(GetPasskeyRegisterOptionsRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $displayName = $user->name ?? $user->email;
            $identifier = $user->getKey();

            $options = $this->passkeyService->getRegistrationOptions(
                (string) $identifier,
                $user->{config('passkeys.user_lookup_field', 'email')},
                $displayName
            );

            $options = Passkey::webAuthnSerializer()->serialize($options, 'json');
            $sessionId = 'reg_' . Str::uuid()->toString();
            Cache::add($sessionId, $options, now()->addMinutes(5));

            return $this->success([
                'options' => $options,
                'session_id' => $sessionId,
            ]);
        } catch (\Throwable $e) {
            return $this->failure(__('passkeys.something_went_wrong'), 500);
        }
    }

    /**
     * @throws ExceptionInterface
     * @throws InvalidDataException
     */
    public function loginOptions(GetPasskeyLoginOptionsRequest $request): JsonResponse
    {
        try {
            $userId = null;
            $lookupField = config('passkeys.user_lookup_field', 'email');

            if ($request->has($lookupField)) {
                $userModel = config('passkeys.user_model');
                $user = $userModel::where($lookupField, $request->input($lookupField))->first();

                if (!$user || !$this->userCanAuthenticate($user)) {
                    return $this->failure(__('passkeys.invalid_credentials'));
                }

                $userId = $user->getKey();
            }

            $options = $this->passkeyService->getLoginOptions($userId);
            $options = Passkey::webAuthnSerializer()->serialize($options, 'json');

            $sessionId = 'log_' . Str::uuid()->toString();
            Cache::add(
                $sessionId,
                json_encode([
                    'options' => $options,
                    'userHandle' => $userId !== null ? (string) $userId : null,
                ]),
                now()->addMinutes(5)
            );

            return $this->success([
                'options' => $options,
                'session_id' => $sessionId,
            ]);
        } catch (\Throwable $e) {
            return $this->failure(__('passkeys.something_went_wrong'), 500);
        }
    }

    /**
     * @throws ValidationException
     * @throws ExceptionInterface
     */
    public function login(VerifyPasskeyRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $cached = Cache::pull($data['session_id']);
            if (is_null($cached)) {
                return $this->failure(__('passkeys.invalid_session_id'));
            }

            $decoded = json_decode($cached, true);
            if (is_array($decoded) && isset($decoded['options'])) {
                $options = $decoded['options'];
                $userHandle = $decoded['userHandle'] ?? null;
            } else {
                $options = $cached;
                $userHandle = null;
            }

            $passkey = $this->passkeyService->verifyPasskey(
                $data['passkey'],
                $options,
                $request->getHost(),
                $userHandle
            );

            $user = $passkey->owner;

            if (!$this->userCanAuthenticate($user)) {
                return $this->failure(__('passkeys.invalid_credentials'), 401);
            }

            $guard = config('passkeys.guard', 'web');
            Auth::guard($guard)->login($user);

            $responseData = ['user' => $user];

            if (method_exists($user, 'createToken')) {
                $token = $user->createToken('passkey-auth');
                $responseData['token'] = $token->accessToken ?? $token->plainTextToken;
                $responseData['token_type'] = 'Bearer';
            }

            return $this->success($responseData, __('passkeys.login_successful'));
        } catch (PasskeyException $e) {
            return $this->failure($e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->failure(__('passkeys.something_went_wrong'), 500);
        }
    }

    /**
     * @throws ExceptionInterface
     * @throws \Throwable
     */
    public function register(RegisterPasskeyRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $options = Cache::pull($data['session_id']);
            if (is_null($options)) {
                return $this->failure(__('passkeys.invalid_session_id'));
            }

            $publicKeyCredentialSource = $this->passkeyService->getPublicKeyCredentialSource(
                $data['passkey'],
                $options,
                $request->getHost()
            );

            $user = $request->user();
            $credentialId = $this->passkeyService->getCredentialId($publicKeyCredentialSource);

            $exists = $user->passkeys()
                ->where('credential_id', $credentialId)
                ->exists();

            if ($exists) {
                return $this->failure(__('passkeys.already_exists'));
            }

            $user->passkeys()->create([
                'name' => $data['name'],
                'credential_id' => $credentialId,
                'data' => Passkey::webAuthnSerializer()->serialize($publicKeyCredentialSource, 'json'),
            ]);

            return $this->success(message: __('passkeys.created'));
        } catch (\Throwable $e) {
            return $this->failure(__('passkeys.something_went_wrong'), 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $passkeys = $request->user()->passkeys;

            return $this->success(
                ['passkeys' => PasskeyResource::collection($passkeys)],
                __('passkeys.loaded')
            );
        } catch (\Throwable $e) {
            return $this->failure(__('passkeys.something_went_wrong'), 500);
        }
    }

    public function destroy(Request $request, $passkeyId): JsonResponse
    {
        try {
            $passkey = $request->user()->passkeys()->find($passkeyId);

            if (!$passkey) {
                return $this->failure(__('passkeys.not_found'), 404);
            }

            $passkey->delete();

            return $this->success(statusCode: 204);
        } catch (\Throwable $e) {
            return $this->failure(__('passkeys.something_went_wrong'), 500);
        }
    }

    private function userCanAuthenticate(mixed $user): bool
    {
        $check = config('passkeys.user_active_check');

        if (is_callable($check)) {
            return $check($user);
        }

        if (is_string($check) && method_exists($user, $check)) {
            return $user->{$check}();
        }

        return true;
    }
}
