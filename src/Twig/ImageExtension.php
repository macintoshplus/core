<?php

declare(strict_types=1);

namespace Bolt\Twig;

use Bolt\Common\Str;
use Bolt\Configuration\Config;
use Bolt\Entity\Content;
use Bolt\Entity\Field\ImageField;
use Bolt\Entity\Media;
use Bolt\Repository\MediaRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class ImageExtension extends AbstractExtension
{
    /** @var Config */
    private $config;

    /** @var MediaRepository */
    private $mediaRepository;

    /** @var Notifications */
    private $notifications;

    public function __construct(Config $config, MediaRepository $mediaRepository, Notifications $notifications)
    {
        $this->config = $config;
        $this->mediaRepository = $mediaRepository;
        $this->notifications = $notifications;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {
        $safe = [
            'is_safe' => ['html'],
        ];

        return [
            new TwigFilter('popup', [$this, 'popup'], $safe),
            new TwigFilter('showimage', [$this, 'showImage'], $safe),
            new TwigFilter('thumbnail', [$this, 'thumbnail'], $safe),
            new TwigFilter('media', [$this, 'getMedia']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        $safe = [
            'is_safe' => ['html'],
        ];

        return [
            new TwigFunction('popup', [$this, 'popup'], $safe),
            new TwigFunction('showimage', [$this, 'showImage'], $safe),
            new TwigFunction('thumbnail', [$this, 'thumbnail'], $safe),
            new TwigFunction('media', [$this, 'media']),
        ];
    }

    public function popup($image, int $width = 320, int $height = 240): string
    {
        $link = $this->getFilename($image);
        $thumbnail = $this->thumbnail($image, $width, $height);
        $alt = $this->getAlt($image);

        return sprintf('<a href="%s" class="bolt-popup"><img src="%s" alt="%s"></a>', $link, $thumbnail, $alt);
    }

    /**
     * @param ImageField|array|string $image
     */
    public function showImage($image, ?int $width = null, ?int $height = null): string
    {
        $link = $this->getFilename($image);
        $alt = $this->getAlt($image);

        if ($width) {
            $width = sprintf('width="%s"', $width);
        }
        if ($height) {
            $height = sprintf('height="%s"', $height);
        }

        return sprintf('<img src="%s" alt="%s" %s %s>', $link, $alt, (string) $width, (string) $height);
    }

    /**
     * @param ImageField|array|string $image
     */
    public function thumbnail($image, int $width = 320, int $height = 240, ?string $location = null, ?string $path = null, ?string $fit = null)
    {
        $filename = Str::ensureStartsWith($this->getFilename($image, true), '/');
        $paramString = $this->buildParams($width, $height, $location, $path, $fit);

        return sprintf('/thumbs/%s%s', $paramString, $filename);
    }

    private function buildParams(int $width, int $height, ?string $location = null, ?string $path = null, ?string $fit = null): string
    {
        $paramString = sprintf('%s×%s', $width, $height);

        if ($fit) {
            $paramString .= '×fit=' . $fit;
        }

        if ($location) {
            $paramString .= '×location=' . $location;
        }

        if ($path) {
            $paramString .= '×path=' . $path;
        }

        return $paramString;
    }

    /**
     * @param ImageField|array $image
     */
    public function getMedia($image): ?Media
    {
        if (is_array($image) && array_key_exists('media', $image)) {
            return $this->mediaRepository->findOneBy(['id' => $image['media']]);
        }

        if ($image instanceof ImageField) {
            return $image->getLinkedMedia($this->mediaRepository);
        }

        return $this->notifications->warning(
            'Incorrect usage of `media`-filter',
            'The `media`-filter can only be applied to an `ImageField`, or an array that has a key named `media` which holds an id.'
        );
    }

    /**
     * @param ImageField|Content|array|string $image
     */
    private function getFilename($image, bool $relative = false): ?string
    {
        $filename = null;

        if ($image instanceof Content) {
            $image = $this->getImageFromContent($image);
        }

        if (is_array($image)) {
            $filename = $image['filename'];
        } elseif ($relative && ($image instanceof ImageField)) {
            $filename = $image->get('filename');
        } else {
            $filename = (string) $image;
        }

        return $filename;
    }

    /**
     * @param ImageField|Content|array|string $image
     */
    private function getAlt($image): string
    {
        $alt = '';

        if ($image instanceof Content) {
            $image = $this->getImageFromContent($image);
        }

        if ($image instanceof ImageField) {
            $alt = $image->get('alt');
        } elseif (is_array($image)) {
            $alt = $image['alt'];
        } elseif (is_string($image)) {
            $alt = $image;
        }

        return htmlentities((string) $alt, ENT_QUOTES);
    }

    private function getImageFromContent(Content $content): ?ImageField
    {
        foreach ($content->getFields() as $field) {
            if ($field instanceof ImageField) {
                return $field;
            }
        }

        return null;
    }
}
