<?php

namespace App\Http\Controllers\Api\V1\Appointment;

use App\Http\Controllers\Controller;
use App\Models\Saloon;
use App\Models\User;
use App\Services\Appointment\AppointmentImportService;
use App\Support\Appointment\AppointmentImportAccess;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AppointmentImportController extends Controller
{
    public function __construct(
        private readonly AppointmentImportService $imports,
    ) {}

    public function sample(): Response
    {
        /** @var User $user */
        $user = request()->user();
        AppointmentImportAccess::assert($user);

        return response($this->imports->sampleCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="saloenza-appointment-import-sample.csv"',
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        AppointmentImportAccess::assert($user);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'saloon_id' => ['nullable', 'integer', 'exists:saloons,id'],
        ]);

        $extension = strtolower((string) $request->file('file')?->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt'], true)) {
            throw ValidationException::withMessages([
                'file' => 'Upload a CSV file.',
            ]);
        }

        $saloon = $this->saloon($request, $user);
        $contents = (string) file_get_contents($request->file('file')->getRealPath());

        return response()->json([
            'message' => 'Appointment file checked.',
            'data' => $this->imports->preview($user, $saloon, $contents),
        ]);
    }

    public function commit(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        AppointmentImportAccess::assert($user);

        $validated = $request->validate([
            'token' => ['required', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'saloon_id' => ['nullable', 'integer', 'exists:saloons,id'],
        ]);

        $saloon = $this->saloon($request, $user);

        return response()->json([
            'message' => 'Appointment import batch processed.',
            'data' => $this->imports->commitBatch(
                $user,
                $saloon,
                (string) $validated['token'],
                (int) ($validated['limit'] ?? 20),
            ),
        ]);
    }

    private function saloon(Request $request, User $user): Saloon
    {
        $requested = $request->filled('saloon_id') ? (int) $request->integer('saloon_id') : null;

        if ($user->is_system_admin && $requested === null) {
            throw ValidationException::withMessages([
                'saloon_id' => 'Select the salon this file belongs to.',
            ]);
        }

        $saloonId = TenantScope::resolveSaloonId($user, $requested);
        $saloon = Saloon::query()->find($saloonId);

        if ($saloon === null) {
            throw ValidationException::withMessages([
                'saloon_id' => 'Selected salon was not found.',
            ]);
        }

        return $saloon;
    }
}
