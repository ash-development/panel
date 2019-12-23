<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Pterodactyl\Services\Users\TwoFactorSetupService;
use Pterodactyl\Services\Users\ToggleTwoFactorService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TwoFactorController extends ClientApiController
{
    /**
     * @var \Pterodactyl\Services\Users\TwoFactorSetupService
     */
    private $setupService;

    /**
     * @var \Illuminate\Contracts\Validation\Factory
     */
    private $validation;

    /**
     * @var \Pterodactyl\Services\Users\ToggleTwoFactorService
     */
    private $toggleTwoFactorService;

    /**
     * TwoFactorController constructor.
     *
     * @param \Pterodactyl\Services\Users\ToggleTwoFactorService $toggleTwoFactorService
     * @param \Pterodactyl\Services\Users\TwoFactorSetupService $setupService
     * @param \Illuminate\Contracts\Validation\Factory $validation
     */
    public function __construct(
        ToggleTwoFactorService $toggleTwoFactorService,
        TwoFactorSetupService $setupService,
        Factory $validation
    ) {
        parent::__construct();

        $this->setupService = $setupService;
        $this->validation = $validation;
        $this->toggleTwoFactorService = $toggleTwoFactorService;
    }

    /**
     * Returns two-factor token credentials that allow a user to configure
     * it on their account. If two-factor is already enabled this endpoint
     * will return a 400 error.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function index(Request $request)
    {
        if ($request->user()->totp_enabled) {
            throw new BadRequestHttpException('Two-factor authentication is already enabled on this account.');
        }

        return JsonResponse::create([
            'data' => [
                'image_url_data' => $this->setupService->handle($request->user()),
            ],
        ]);
    }

    /**
     * Updates a user's account to have two-factor enabled.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \PragmaRX\Google2FA\Exceptions\IncompatibleWithGoogleAuthenticatorException
     * @throws \PragmaRX\Google2FA\Exceptions\InvalidCharactersException
     * @throws \PragmaRX\Google2FA\Exceptions\SecretKeyTooShortException
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     * @throws \Pterodactyl\Exceptions\Service\User\TwoFactorAuthenticationTokenInvalid
     */
    public function store(Request $request)
    {
        $validator = $this->validation->make($request->all(), [
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $this->toggleTwoFactorService->handle($request->user(), $request->input('code'), true);

        return JsonResponse::create([], Response::HTTP_NO_CONTENT);
    }

    public function delete()
    {
    }
}
