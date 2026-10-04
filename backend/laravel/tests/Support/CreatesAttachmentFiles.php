<?php

namespace Tests\Support;

use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;

trait CreatesAttachmentFiles
{
    protected function jpegUpload(string $name = 'photo.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->imageBytes('jpeg'));
    }

    protected function pngUpload(string $name = 'photo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->imageBytes('png'));
    }

    protected function webpUpload(string $name = 'photo.webp'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->imageBytes('webp'));
    }

    protected function pdfUpload(string $name = 'document.pdf'): UploadedFile
    {
        return $this->fakeFile($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
    }

    protected function textUpload(string $name = 'notes.txt'): UploadedFile
    {
        return $this->fakeFile($name, 'just plain text');
    }

    protected function fakeFile(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function imageBytes(string $format): string
    {
        $image = imagecreatetruecolor(12, 12);
        ob_start();

        match ($format) {
            'png' => imagepng($image),
            'webp' => imagewebp($image),
            default => imagejpeg($image),
        };

        $content = (string) ob_get_clean();
        imagedestroy($image);

        return $content;
    }

    /** @return File */
    protected function zeroByteUpload(string $name = 'empty.png'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 0);
    }
}
