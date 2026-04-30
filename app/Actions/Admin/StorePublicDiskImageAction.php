<?php

namespace App\Actions\Admin;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorePublicDiskImageAction
{
    public function execute(UploadedFile $file, string $subDirectory): string
    {
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $filename = $safeName.'-'.Str::random(8).'.'.$ext;
        $path = trim($subDirectory, '/').'/'.now()->format('Y/m').'/'.$filename;

        Storage::disk('public')->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        return $path;
    }
}
