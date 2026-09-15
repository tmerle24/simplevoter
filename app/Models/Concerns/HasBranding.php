<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Eigenes Branding (Logo + Haupt-/Akzentfarbe) für Event bzw. Poll.
 * Logo liegt auf dem public-Disk und wird beim Löschen des Models mit entfernt.
 */
trait HasBranding
{
    public static function bootHasBranding(): void
    {
        static::deleting(function ($model) {
            $model->deleteBrandLogo();
        });
    }

    public function initializeHasBranding(): void
    {
        $this->mergeFillable(['brand_logo_path', 'brand_primary_color', 'brand_accent_color']);
    }

    public function deleteBrandLogo(): void
    {
        if ($this->brand_logo_path) {
            Storage::disk('public')->delete($this->brand_logo_path);
        }
    }

    public function brandingPayload(): array
    {
        return [
            // relativ statt Storage::url(), damit APP_URL-Abweichungen egal sind
            'logo_url' => $this->brand_logo_path ? '/storage/'.$this->brand_logo_path : null,
            'primary_color' => $this->brand_primary_color,
            'accent_color' => $this->brand_accent_color,
        ];
    }
}
