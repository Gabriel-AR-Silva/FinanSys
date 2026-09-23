<?php

namespace App\Http\Requests;

class UpdateFinancialGoalRequest extends StoreFinancialGoalRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['operation_id']);

        return $rules;
    }
}
