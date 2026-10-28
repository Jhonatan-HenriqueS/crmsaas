<?php

namespace App\Http\Requests;

use App\Http\CurrentAccount;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdateAccountGoalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::authorize('update', app(CurrentAccount::class)->get())->allowed();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'goal_amount' => ['bail', 'required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'deadline_days' => ['bail', 'required_without:deadline_date', 'prohibits:deadline_date', 'integer', 'between:1,3650'],
            'deadline_date' => ['bail', 'required_without:deadline_days', 'string', 'date_format:Y-m-d', 'after_or_equal:'.$this->today()->toDateString()],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->except('_token')), array_keys($this->rules())) !== []) {
                $validator->errors()->add('form', 'Envie apenas os dados permitidos da meta.');
            }
        }];
    }

    /** @return array{goal_amount: string, goal_deadline: string} */
    public function goal(): array
    {
        $validated = $this->validated();

        return [
            'goal_amount' => (string) $validated['goal_amount'],
            'goal_deadline' => isset($validated['deadline_days'])
                ? $this->today()->addDays((int) $validated['deadline_days'])->toDateString()
                : $validated['deadline_date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'goal_amount.required' => 'Informe o valor da meta.',
            'goal_amount.numeric' => 'Informe um valor numérico válido para a meta.',
            'goal_amount.decimal' => 'O valor da meta deve ter no máximo duas casas decimais.',
            'goal_amount.gt' => 'O valor da meta deve ser maior que zero.',
            'goal_amount.max' => 'O valor da meta não pode exceder 9999999999.99.',
            'deadline_days.required_without' => 'Informe o prazo da meta em dias ou escolha uma data.',
            'deadline_days.prohibits' => 'Informe o prazo em dias ou por data, não os dois.',
            'deadline_days.integer' => 'Informe o prazo em dias como um número inteiro.',
            'deadline_days.between' => 'O prazo deve ficar entre 1 e 3650 dias.',
            'deadline_date.required_without' => 'Escolha a data limite da meta.',
            'deadline_date.string' => 'Informe a data limite no formato AAAA-MM-DD.',
            'deadline_date.date_format' => 'Informe uma data limite válida no formato AAAA-MM-DD.',
            'deadline_date.after_or_equal' => 'A data limite da meta deve ser hoje ou uma data futura.',
        ];
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('reporting.timezone'))->startOfDay();
    }
}
