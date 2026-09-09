<?php

namespace App\Http\Requests\Api\V1\Saloon;

use App\Models\Saloon;
use App\Support\Saloon\SaloonAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncSaloonServiceProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Saloon $saloon */
        $saloon = $this->route('saloon');

        SaloonAccess::ensureWritable($this->user(), $saloon);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_products' => ['present', 'array'],
            'service_products.*.branch_id' => ['nullable', 'integer', 'exists:saloon_branches,id'],
            'service_products.*.service_id' => ['required', 'integer', 'exists:services,id'],
            'service_products.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'service_products.*.price' => ['required', 'numeric', 'min:0'],
            'service_products.*.duration_minutes' => ['required', 'integer', 'min:1'],
            'service_products.*.is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $serviceProducts = $this->input('service_products', []);

            if (! is_array($serviceProducts)) {
                return;
            }

            $seen = [];

            foreach ($serviceProducts as $index => $serviceProduct) {
                if (! is_array($serviceProduct)) {
                    continue;
                }

                $branchId = $serviceProduct['branch_id'] ?? null;
                $serviceId = $serviceProduct['service_id'] ?? null;
                $productId = $serviceProduct['product_id'] ?? null;
                $key = ($branchId === null ? 'null' : (string) $branchId)
                    .":{$serviceId}:".($productId === null ? 'null' : (string) $productId);

                if (isset($seen[$key])) {
                    $validator->errors()->add(
                        "service_products.{$index}.service_id",
                        'Duplicate service and product combination in request.',
                    );
                }

                $seen[$key] = true;
            }
        });
    }
}
