<?php
namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RollbackTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
             'transaction_id' => ['required', 'integer', 'exists:ingredient_transactions,id']
        ];
    }
}
