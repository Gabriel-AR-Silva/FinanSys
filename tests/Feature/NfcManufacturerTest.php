<?php

namespace Tests\Feature;

use App\Models\NfcAccessLink;
use App\Models\NfcRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NfcManufacturerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_manufacturer_images_can_be_uploaded_previewed_downloaded_and_removed(): void
    {
        $owner = User::factory()->create();
        $manufacturer = $this->createManufacturer($owner);

        $this->post(route('nfc.manufacturers.images.store', $manufacturer->id), [
            'images' => [$this->png('cartao.png'), $this->png('plaquinha.png')],
        ])->assertRedirect(route('nfc.index'));

        $manufacturer->refresh();
        $this->assertCount(2, $manufacturer->payload['images']);
        $first = $manufacturer->payload['images'][0];
        Storage::disk('local')->assertExists($first['path']);

        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->has('manufacturers', 1)
            ->where('manufacturers.0.id', $manufacturer->id)
            ->has('manufacturers.0.images', 2)
            ->missing('manufacturers.0.images.0.path'));

        $url = route('nfc.manufacturers.images.show', ['manufacturer' => $manufacturer->id, 'image' => $first['id']]);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get($url.'?download=1')->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=cartao.png');

        $this->delete(route('nfc.manufacturers.images.destroy', [
            'manufacturer' => $manufacturer->id,
            'image' => $first['id'],
        ]))->assertRedirect(route('nfc.index'));

        Storage::disk('local')->assertMissing($first['path']);
        $this->assertCount(1, $manufacturer->fresh()->payload['images']);
        $this->get($url)->assertNotFound();
    }

    public function test_ten_image_cap_is_enforced_and_rejected_files_are_cleaned_up(): void
    {
        $owner = User::factory()->create();
        $manufacturer = $this->createManufacturer($owner);

        $files = [];
        for ($i = 0; $i < 9; $i++) {
            $files[] = $this->png("cartao-{$i}.png");
        }
        $this->post(route('nfc.manufacturers.images.store', $manufacturer->id), ['images' => $files])
            ->assertRedirect(route('nfc.index'));

        $folder = "nfc/manufacturers/{$owner->id}/{$manufacturer->id}";
        $this->assertCount(9, Storage::disk('local')->allFiles($folder));

        $this->post(route('nfc.manufacturers.images.store', $manufacturer->id), [
            'images' => [$this->png('decima.png'), $this->png('extra.png')],
        ])->assertSessionHasErrors('images');

        $this->assertCount(9, $manufacturer->fresh()->payload['images']);
        $this->assertCount(9, Storage::disk('local')->allFiles($folder));

        $this->post(route('nfc.manufacturers.images.store', $manufacturer->id), [
            'images' => [$this->png('decima.png')],
        ])->assertRedirect(route('nfc.index'));

        $this->assertCount(10, $manufacturer->fresh()->payload['images']);

        $this->post(route('nfc.manufacturers.images.store', $manufacturer->id), [
            'images' => [$this->png('extra.png')],
        ])->assertSessionHasErrors('images');

        $this->assertCount(10, Storage::disk('local')->allFiles($folder));
    }

    public function test_manufacturer_images_are_isolated_and_shared_tokens_follow_revocation(): void
    {
        $owner = User::factory()->create();
        $manufacturer = $this->createManufacturer($owner);
        $this->post(route('nfc.manufacturers.images.store', $manufacturer->id), [
            'images' => [$this->png('referencia.png')],
        ])->assertRedirect(route('nfc.index'));
        $imageId = $manufacturer->fresh()->payload['images'][0]['id'];

        $other = User::factory()->create();
        $this->actingAs($other);
        $this->get(route('nfc.manufacturers.images.show', ['manufacturer' => $manufacturer->id, 'image' => $imageId]))
            ->assertNotFound();
        $this->post(route('nfc.manufacturers.images.store', $manufacturer->id), [
            'images' => [$this->png('intruso.png')],
        ])->assertNotFound();

        $this->actingAs($owner)->post(route('nfc.share-links.store'))->assertRedirect(route('nfc.index'));
        preg_match('~/compartilhado/([^/?]+)~', session('nfc_share_url'), $matches);
        $token = $matches[1];

        Auth::logout();
        $sharedImageUrl = route('nfc.share.manufacturers.images.show', [
            'token' => $token, 'manufacturer' => $manufacturer->id, 'image' => $imageId,
        ]);
        $this->get($sharedImageUrl)->assertOk();

        $this->actingAs($owner)->delete(route('nfc.share-links.destroy', [
            'link' => NfcAccessLink::query()->where('user_id', $owner->id)->firstOrFail()->id,
        ]))->assertRedirect(route('nfc.index'));
        Auth::logout();
        $this->get($sharedImageUrl)->assertNotFound();
    }

    public function test_manufacturer_lot_assignment_populates_financial_and_unit_indicators(): void
    {
        $owner = User::factory()->create();
        $manufacturer = $this->createManufacturer($owner);
        $this->post(route('nfc.production.store'), [
            'operation_id' => (string) Str::uuid(),
            'initial' => false,
            'manufacturer_id' => $manufacturer->id,
            'produced_on' => now('America/Sao_Paulo')->toDateString(),
            'quantity' => 15,
            'customized_quantity' => 5,
            'cost_cents' => 7500,
            'status' => 'completed',
        ])->assertRedirect(route('nfc.index'));

        $batch = NfcRecord::query()->whereBelongsTo($owner)->where('kind', NfcRecord::PRODUCTION)->firstOrFail();
        $this->post(route('nfc.companies.store'), [
            'operation_id' => (string) Str::uuid(),
            'name' => 'Empresa vinculada',
            'interested' => true,
        ])->assertRedirect(route('nfc.index'));
        $company = NfcRecord::query()->whereBelongsTo($owner)->where('kind', NfcRecord::COMPANY)->firstOrFail();
        $unit = $company->payload['units'][0];

        $this->patch(route('nfc.units.update', ['company' => $company->id, 'unit' => $unit['id']]), [
            'name' => $unit['name'], 'production_id' => $batch->id,
        ])->assertRedirect(route('nfc.index'));

        $this->post(route('nfc.sales.store', $company->id), [
            'operation_id' => (string) Str::uuid(),
            'unit_id' => $unit['id'],
            'sold_on' => now('America/Sao_Paulo')->toDateString(),
            'total_cents' => 9000,
            'standard_quantity' => 2,
            'custom_quantity' => 1,
            'payments' => [['method' => 'pix', 'amount_cents' => 5000]],
        ])->assertRedirect(route('nfc.index'));

        $this->get(route('nfc.index'))->assertInertia(fn (Assert $page) => $page
            ->where('manufacturers.0.statistics.batches', 1)
            ->where('manufacturers.0.statistics.units', 1)
            ->where('manufacturers.0.statistics.manufactured', 15)
            ->where('manufacturers.0.statistics.sold', 3)
            ->where('manufacturers.0.statistics.received_cents', 5000)
            ->where('manufacturers.0.statistics.outstanding_cents', 4000)
            ->where('manufacturers.0.statistics.production_cost_cents', 7500));

        $this->delete(route('nfc.manufacturers.destroy', $manufacturer->id))
            ->assertSessionHasErrors('manufacturer');
    }

    private function createManufacturer(User $owner): NfcRecord
    {
        $this->actingAs($owner)->post(route('nfc.manufacturers.store'), [
            'operation_id' => (string) Str::uuid(),
            'name' => 'Fornecedor de plaquinhas',
            'phone' => '18 3333-1111',
        ])->assertRedirect(route('nfc.index'));

        return NfcRecord::query()->whereBelongsTo($owner)->where('kind', NfcRecord::MANUFACTURER)->firstOrFail();
    }

    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAC0lEQVR4nGNgQAYAAA4AAamRc7EAAAAASUVORK5CYII=',
        ));
    }
}
