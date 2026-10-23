<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePolicyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'client_request_id' => ['required', 'string', 'max:128'],
            'agent_id' => ['required', 'string', 'max:64'],
            'customer_name' => ['required', 'string', 'min:2', 'max:255'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'destination' => ['required', 'string', 'in:DOMESTIC,INTERNATIONAL'],
            'trip_start' => ['required', 'date_format:Y-m-d'],
            'trip_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:trip_start'],
            'plan_code' => ['required', 'string', 'in:BASIC,PLUS,PREMIUM'],
            'sum_insured' => ['required', 'in:100000,300000,500000,100000.00,300000.00,500000.00'],
        ];
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_request_id.required' => 'The client_request_id field is required.',
            'agent_id.required' => 'The agent_id field is required.',
            'customer_name.required' => 'The customer_name field is required.',
            'date_of_birth.date_format' => 'The date_of_birth must be a valid date in Y-m-d format.',
            'date_of_birth.before' => 'The date_of_birth must be a date before today.',
            'destination.in' => 'The destination must be either DOMESTIC or INTERNATIONAL.',
            'trip_start.date_format' => 'The trip_start must be a valid date in Y-m-d format.',
            'trip_end.date_format' => 'The trip_end must be a valid date in Y-m-d format.',
            'trip_end.after_or_equal' => 'The trip_end date must not be earlier than trip_start.',
            'plan_code.in' => 'The plan_code must be one of: BASIC, PLUS, PREMIUM.',
            'sum_insured.in' => 'The sum_insured must be one of: 100000, 300000, 500000.',
        ];
    }

    /**
     * Standardized JSON envelope for validation errors.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'The given policy intake payload is invalid.',
                'fields' => $validator->errors(),
            ],
        ], 422));
    }
}
