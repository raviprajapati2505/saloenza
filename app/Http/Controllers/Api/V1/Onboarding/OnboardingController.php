<?php

namespace App\Http\Controllers\Api\V1\Onboarding;

use App\Actions\Onboarding\OnboardingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Onboarding\AccountStepRequest;
use App\Http\Requests\Api\V1\Onboarding\BranchStepRequest;
use App\Http\Requests\Api\V1\Onboarding\CompleteStepRequest;
use App\Http\Requests\Api\V1\Onboarding\ServicesStepRequest;
use App\Http\Resources\Api\V1\Common\MessageResponseResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingAction $onboardingAction,
    ) {
    }

    public function account(AccountStepRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->onboardingAction->saveAccount($user, $request->validated());

        return (new MessageResponseResource([
            'message' => 'Account step saved.',
        ]))->response();
    }

    public function branch(BranchStepRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->onboardingAction->saveBranch($user, $request->validated());

        return (new MessageResponseResource([
            'message' => 'Branch step saved.',
        ]))->response();
    }

    public function services(ServicesStepRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->onboardingAction->saveServices($user, $request->validated());

        return (new MessageResponseResource([
            'message' => 'Services step saved.',
        ]))->response();
    }

    public function complete(CompleteStepRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->onboardingAction->complete($user);

        return (new MessageResponseResource([
            'message' => 'Onboarding completed.',
        ]))->response();
    }
}
