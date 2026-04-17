<?php
// src/Service/CloudinaryService.php

namespace App\Service;

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Api\Exception\ApiError;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Psr\Log\LoggerInterface;

class CloudinaryService
{
    private UploadApi $uploadApi;

    public function __construct(
        #[Autowire(env: 'CLOUDINARY_URL')]
        private string $cloudinaryUrl,
        private ?LoggerInterface $logger = null,
    ) {
        Configuration::instance($this->cloudinaryUrl);
        $this->uploadApi = new UploadApi();
    }

    public function upload(UploadedFile $file, string $folder = 'profiles'): string
    {
        try {
            $result = $this->uploadApi->upload($file->getRealPath(), [
                'folder' => $folder,
                'public_id' => uniqid('user_', true),
                'use_filename' => false,
                'unique_filename' => true,
                'overwrite' => false,
                'resource_type' => 'image',
            ]);

            $this->logger?->info('Cloudinary upload success', [
                'file' => $file->getClientOriginalName(),
                'url' => $result['secure_url'] ?? null,
            ]);

            return $result['secure_url'];
            
        } catch (\Exception $e) {
            $this->logger?->error('Cloudinary upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);
            throw new \RuntimeException('Upload failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function uploadFromUrl(string $url, string $folder = 'profiles'): string
    {
        try {
            $result = $this->uploadApi->upload($url, [
                'folder' => $folder,
                'public_id' => uniqid('google_', true),
                'use_filename' => false,
                'unique_filename' => true,
                'resource_type' => 'image',
            ]);

            $this->logger?->info('Cloudinary upload from URL success', [
                'source' => $url,
                'url' => $result['secure_url'] ?? null,
            ]);

            return $result['secure_url'];
            
        } catch (\Exception $e) {
            $this->logger?->error('Cloudinary upload from URL failed', [
                'error' => $e->getMessage(),
                'source' => $url,
            ]);
            throw new \RuntimeException('Upload from URL failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function deleteByUrl(string $cloudinaryUrl): void
    {
        try {
            if (!preg_match('#/image/upload/(?:v\d+/)?(.+)$#', $cloudinaryUrl, $matches)) {
                return;
            }
            $publicId = $matches[1];
            $this->uploadApi->destroy($publicId);
        } catch (\Exception $e) {
            $this->logger?->error('Cloudinary delete failed', ['error' => $e->getMessage()]);
        }
    }
}