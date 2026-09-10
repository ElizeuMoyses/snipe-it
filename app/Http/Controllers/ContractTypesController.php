<?php

namespace App\Http\Controllers;

use App\Models\ContractType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContractTypesController extends Controller
{
    public function index(): View
    {
        $this->authorize('view', ContractType::class);

        return view('contract-types/index');
    }

    public function create(): View
    {
        $this->authorize('create', ContractType::class);

        return view('contract-types/edit')->with('item', new ContractType(['is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ContractType::class);

        try {
            $data = $this->validatedInput($request);
        } catch (ValidationException $exception) {
            return redirect()->back()->withInput()->withErrors($exception->errors());
        }

        $type = new ContractType;
        $type->fill($data);
        $type->created_by = auth()->id();

        if ($type->save()) {
            return redirect()->route('contract-types.index')
                ->with('success', trans('admin/contract_types/message.create.success'));
        }

        return redirect()->back()->withInput()->withErrors($type->getErrors());
    }

    public function show(ContractType $contract_type): View
    {
        $this->authorize('view', $contract_type);

        return view('contract-types/view')->with('item', $contract_type->loadCount('contracts'));
    }

    public function edit(ContractType $contract_type): View
    {
        $this->authorize('update', $contract_type);

        return view('contract-types/edit')->with('item', $contract_type);
    }

    public function update(Request $request, ContractType $contract_type): RedirectResponse
    {
        $this->authorize('update', $contract_type);

        try {
            $data = $this->validatedInput($request, $contract_type);
        } catch (ValidationException $exception) {
            return redirect()->back()->withInput()->withErrors($exception->errors());
        }

        $contract_type->fill($data);

        if ($contract_type->save()) {
            return redirect()->route('contract-types.index')
                ->with('success', trans('admin/contract_types/message.update.success'));
        }

        return redirect()->back()->withInput()->withErrors($contract_type->getErrors());
    }

    public function destroy(ContractType $contract_type): RedirectResponse
    {
        $this->authorize('delete', $contract_type);

        if (! $contract_type->isDeletable()) {
            return redirect()->route('contract-types.index')
                ->with('error', trans('admin/contract_types/message.assoc_contracts'));
        }

        $contract_type->delete();

        return redirect()->route('contract-types.index')
            ->with('success', trans('admin/contract_types/message.delete.success'));
    }

    private function validatedInput(Request $request, ?ContractType $type = null): array
    {
        $input = $request->only(['name', 'code', 'is_active', 'notes']);
        if ($request->filled('code')) {
            $input['code'] = Str::lower(trim((string) $request->input('code')));
        } elseif (! $type) {
            $input['code'] = Str::slug((string) $request->input('name'), '_');
        } else {
            $input['code'] = $type->code;
        }

        // The web form is a complete edit form: an unchecked box means
        // explicitly inactive. API PATCH keeps its separate omission behavior.
        $input['is_active'] = $type
            ? $request->boolean('is_active')
            : ($request->has('is_active') ? $request->boolean('is_active') : true);

        $uniqueCode = Rule::unique('contract_types', 'code')
            ->whereNull('deleted_at');
        if ($type) {
            $uniqueCode->ignore($type->id);
        }

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/', $uniqueCode],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $input;
    }
}
