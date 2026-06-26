<?php

declare(strict_types=1);

namespace Xpressengine\Plugins\Orientator\Tests;

use Intervention\Image\ImageManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File as BaseFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Xpressengine\Media\MediaManager;
use Xpressengine\Plugins\Orientator\Actions\OrientImageFileAction;

require_once dirname(__DIR__).'/src/Actions/OrientImageFileAction.php';

final class OrientImageFileActionTest extends TestCase
{
    public function testExecuteRewritesFileWhenOrientationExifExists()
    {
        $sourcePath = dirname(__DIR__).'/assets/orientator-ifd0-orientation-6.jpg';
        $pathname = tempnam(sys_get_temp_dir(), 'orientator-exif-');
        self::assertTrue(is_string($pathname));
        copy($sourcePath, $pathname);

        $originalContent = file_get_contents($pathname);

        try {
            $file = new UploadedFile($pathname, 'orientator-ifd0-orientation-6.jpg', 'image/jpeg', null, true);
            $result = (new OrientImageFileAction($this->makeMediaManager('image/jpeg')))->execute($file);

            self::assertNotSame($file, $result);
            self::assertNotSame($originalContent, file_get_contents($pathname));
            self::assertNotSame(6, $this->readOrientation($pathname));
        } finally {
            if (is_file($pathname)) {
                unlink($pathname);
            }
        }
    }

    public function testExecuteReturnsOriginalBaseFileWhenOrientationExifIsMissing()
    {
        $pathname = tempnam(sys_get_temp_dir(), 'orientator-no-exif-');
        self::assertTrue(is_string($pathname));

        $content = ImageManager::gd()
            ->create(1, 1)
            ->toPng()
            ->toString();

        file_put_contents($pathname, $content);

        try {
            $file = new BaseFile($pathname);
            $result = (new OrientImageFileAction($this->makeMediaManager('image/png')))->execute($file);

            self::assertInstanceOf(BaseFile::class, $result);
            self::assertSame($content, file_get_contents($pathname));
        } finally {
            if (is_file($pathname)) {
                unlink($pathname);
            }
        }
    }

    private function makeMediaManager($availableMime)
    {
        return new class($availableMime) extends MediaManager {
            private $availableMime;

            public function __construct($availableMime)
            {
                $this->availableMime = $availableMime;
            }

            public function getHandler($type)
            {
                return new class($this->availableMime) {
                    private $availableMime;

                    public function __construct($availableMime)
                    {
                        $this->availableMime = $availableMime;
                    }

                    public function isAvailable($mime)
                    {
                        return $mime === $this->availableMime;
                    }
                };
            }
        };
    }

    private function readOrientation($pathname)
    {
        $exif = exif_read_data($pathname, 'IFD0', true);

        if (!is_array($exif)) {
            return null;
        }

        return $exif['IFD0']['Orientation'] ?? null;
    }
}
