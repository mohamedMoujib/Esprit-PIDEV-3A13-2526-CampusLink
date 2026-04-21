<?php

namespace App\Twig;

// src/Twig/AvatarExtension.php

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AvatarExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('avatar_url', [$this, 'getAvatarUrl']),
        ];
    }

    public function getAvatarUrl(?string $profilePicture, string $name): string
    {
        // If user has a Cloudinary picture, use it directly
        if ($profilePicture && str_contains($profilePicture, 'res.cloudinary.com')) {
            return $profilePicture;
        }

        // Otherwise generate one from their name via DiceBear
        return 'https://api.dicebear.com/8.x/initials/svg?' . http_build_query([
            'seed'            => $name,
            'backgroundColor' => '4F46E5',
            'textColor'       => 'ffffff',
            'fontSize'        => 40,
            'bold'            => true,
        ]);
    }
}