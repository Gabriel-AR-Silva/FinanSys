<?php

namespace App\Http\Controllers;

use App\Actions\ManageNfcRecords;
use App\Models\NfcRecord;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class NfcManufacturerController extends Controller
{
    public function store(Request $request, ManageNfcRecords $records): RedirectResponse
    {
        $data = $request->validate($this->rules(true));
        $records->storeManufacturer($this->owner($request), $data);

        return $this->backToNfc($request);
    }

    public function update(Request $request, ManageNfcRecords $records, int $manufacturer): RedirectResponse
    {
        $records->updateManufacturer($this->owner($request), $manufacturer, $request->validate($this->rules(false)));

        return $this->backToNfc($request);
    }

    public function destroy(Request $request, ManageNfcRecords $records, int $manufacturer): RedirectResponse
    {
        $paths = $records->deleteManufacturer($this->owner($request), $manufacturer);
        Storage::disk('local')->delete($paths);

        return $this->backToNfc($request);
    }

    public function upload(Request $request, ManageNfcRecords $records, int $manufacturer): RedirectResponse
    {
        $data = $request->validate([
            'images' => ['required', 'array', 'list', 'min:1', 'max:10'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $owner = $this->owner($request);
        $uploaded = [];

        try {
            foreach ($data['images'] as $file) {
                $id = (string) Str::uuid();
                $path = $file->storeAs(
                    "nfc/manufacturers/{$owner->id}/{$manufacturer}",
                    "{$id}.{$file->extension()}",
                    'local',
                );

                if (! $path) {
                    throw ValidationException::withMessages(['images' => 'Não foi possível armazenar a imagem.']);
                }

                $uploaded[] = [
                    'id' => $id,
                    'name' => Str::limit(basename($file->getClientOriginalName()), 160, ''),
                    'path' => $path,
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }

            $records->attachManufacturerImages($owner, $manufacturer, $uploaded);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete(array_column($uploaded, 'path'));

            throw $exception;
        }

        return $this->backToNfc($request);
    }

    public function showImage(Request $request, int $manufacturer, string $image): BinaryFileResponse
    {
        $owner = $this->owner($request);
        $record = $this->findManufacturer($owner, $manufacturer);
        $entry = collect($record->payload['images'] ?? [])->firstWhere('id', strtolower($image));
        abort_if($entry === null, 404);

        $path = (string) ($entry['path'] ?? '');
        abort_unless(str_starts_with($path, "nfc/manufacturers/{$owner->id}/{$manufacturer}/"), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        $fullPath = Storage::disk('local')->path($path);
        $response = $request->boolean('download')
            ? response()->download($fullPath, $entry['name'] ?? 'imagem.png')
            : response()->file($fullPath, ['Content-Type' => $entry['mime'] ?? 'image/png']);

        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    public function deleteImage(Request $request, ManageNfcRecords $records, int $manufacturer, string $image): RedirectResponse
    {
        $path = $records->removeManufacturerImage($this->owner($request), $manufacturer, $image);
        Storage::disk('local')->delete($path);

        return $this->backToNfc($request);
    }

    private function findManufacturer(User $owner, int $id): NfcRecord
    {
        $record = NfcRecord::query()->whereBelongsTo($owner)
            ->where('kind', NfcRecord::MANUFACTURER)->findOrFail($id);
        abort_if(! empty($record->payload['deleted_at']), 404);

        return $record;
    }

    private function owner(Request $request): User
    {
        return $request->attributes->get('nfc_share_owner') ?? $request->user();
    }

    private function backToNfc(Request $request): RedirectResponse
    {
        return $request->attributes->has('nfc_share_owner')
            ? to_route('nfc.share.index', ['token' => $request->attributes->get('nfc_share_token')])
            : to_route('nfc.index');
    }

    private function rules(bool $create): array
    {
        return [
            ...($create ? ['operation_id' => ['required', 'uuid']] : []),
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
