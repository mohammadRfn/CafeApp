<?php
namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class CreateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', Rule::in(['ingredient', 'box'])],
            'entity_id' => ['required', 'integer', 'min:1'], 
            'transaction_type' => ['required', Rule::in([
                'purchase','usage','adjustment','waste','expiry','reserve','release'
            ])],
            'input_quantity' => ['required', 'numeric', 'min:0.001'],
            
            'grams_effect' => Rule::when(
                $this->input('entity_type') === 'ingredient',
                ['required', 'numeric', 'between:-999999,999999']
            ),
            'quantity_effect' => Rule::when(
                $this->input('entity_type') === 'box',
                ['required', 'numeric', 'between:-999999,999999']
            ),
            
            'total_cost' => ['nullable', 'numeric', 'min:0'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'reference_type' => ['nullable', 'string', 'max:50', 'alpha_dash'],
            'reference_id' => ['nullable', 'integer', 'min:1'],
            'invoice_number' => ['nullable', 'string', 'max:100', 'alpha_num'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'created_by' => ['nullable', 'exists:users,id']
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $entityType = $this->input('entity_type');
            $entityId = $this->input('entity_id');
            
            $table = $entityType === 'ingredient' ? 'ingredients' : 'boxes';
            
            if (!DB::table($table)->where('id', $entityId)->exists()) {
                $validator->errors()->add('entity_id', "Entity #{$entityId} not found in {$entityType}s");
            }
        });
    }
}
