<?php

namespace Tests\Feature\Suppliers;

use App\Models\Supplier;
use App\Models\User;
use Tests\TestCase;

class SupplierFiscalFlowTest extends TestCase
{
    public function test_modal_exposes_the_full_supplier_fields(): void
    {
        $this->actingAs(User::factory()->superuser()->create(['locale'=>'pt-BR']));
        $fields = ['name', 'address', 'address2', 'city', 'state', 'country', 'zip',
            'contact', 'phone', 'fax', 'email', 'url', 'supplier_type', 'document',
            'corporate_name', 'internal_code', 'notes', 'image', 'tag_color'];
        foreach ([route('suppliers.create'), route('modal.show', 'supplier')] as $url) {
            $response = $this->get($url)->assertOk();
            foreach ($fields as $field) {
                $response->assertSee('name="'.$field.'"', false);
            }
        }
    }

    public function test_ui_create_and_update_preserve_fiscal_and_contact_data(): void
    {
        $this->actingAs(User::factory()->superuser()->create(['locale'=>'pt-BR']));
        $payload = ['name'=>'Synthetic supplier', 'supplier_type'=>'pj',
            'document'=>'11.222.333/0001-81', 'corporate_name'=>'Synthetic legal name',
            'internal_code'=>'QA-SUP-1', 'address'=>'Synthetic street', 'city'=>'Test city',
            'state'=>'SP', 'country'=>'BR', 'zip'=>'01000-000', 'email'=>'qa@example.invalid'];
        $this->post(route('suppliers.store'), $payload)->assertSessionHasNoErrors();
        $supplier = Supplier::where('name', $payload['name'])->firstOrFail();
        $this->assertSame('11222333000181', $supplier->document);
        $payload['supplier_type'] = 'pf';
        $payload['document'] = '529.982.247-25';
        $payload['corporate_name'] = 'Updated synthetic name';
        $this->put(route('suppliers.update', $supplier), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('suppliers', ['id'=>$supplier->id, 'document'=>'52998224725',
            'supplier_type'=>'pf', 'corporate_name'=>$payload['corporate_name'],
            'internal_code'=>'QA-SUP-1', 'address'=>'Synthetic street']);
    }

    public function test_api_create_update_and_invalid_document_do_not_overwrite_data(): void
    {
        app()->setLocale('pt-BR');
        $this->actingAsForApi(User::factory()->superuser()->create(['locale'=>'pt-BR']));
        $payload = ['name'=>'Synthetic API supplier', 'supplier_type'=>'pj',
            'document'=>'11.222.333/0001-81', 'corporate_name'=>'Synthetic company',
            'internal_code'=>'QA-API-1'];
        $this->postJson(route('api.suppliers.store'), $payload)->assertStatusMessageIs('success');
        $supplier = Supplier::where('name', $payload['name'])->firstOrFail();
        $this->patchJson(route('api.suppliers.update', $supplier), ['supplier_type'=>'pf'])
            ->assertStatusMessageIs('error')->assertSee('CPF')->assertDontSee('admin/suppliers/validation');
        $this->assertSame('pj', $supplier->fresh()->supplier_type);
        $this->patchJson(route('api.suppliers.update', $supplier),
            ['supplier_type'=>'pf', 'document'=>'529.982.247-25', 'corporate_name'=>'Updated API name'])
            ->assertStatusMessageIs('success');
        $this->assertDatabaseHas('suppliers', ['id'=>$supplier->id, 'document'=>'52998224725',
            'corporate_name'=>'Updated API name', 'internal_code'=>'QA-API-1']);
    }

    public function test_ui_rejects_cnpj_for_individual_with_translated_error_and_retains_input(): void
    {
        app()->setLocale('pt-BR');
        $this->actingAs(User::factory()->superuser()->create(['locale'=>'pt-BR']));
        $payload = ['name'=>'Invalid synthetic supplier', 'supplier_type'=>'pf', 'document'=>'11222333000181'];
        $this->from(route('suppliers.create'))->post(route('suppliers.store'), $payload)
            ->assertSessionHasErrors(['document'=>'Informe um CPF válido para Pessoa Física.'])
            ->assertSessionHasInput('document', $payload['document']);
        $this->assertDatabaseMissing('suppliers', ['name'=>$payload['name']]);
    }
}
