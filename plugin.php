<?php
/**
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     LGPL-2.1
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html
 * @link        https://xpressengine.io
 */

namespace Xpressengine\Plugins\Orientator;

use Xpressengine\Plugin\AbstractPlugin;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Xpressengine\Media\Models\Media;
use Intervention\Image\ImageManager;

class Plugin extends AbstractPlugin
{
    public function boot()
    {
        intercept('XeStorage@upload', 'orientator.orientate', function ($target, $uploaded, $path, $name = null, $disk = null, $user = null) {
            /** @var UploadedFile $uploaded */
            if ($uploaded->isValid()) {
                $mime = $uploaded->getMimeType();

                /** @var \Xpressengine\Media\Handlers\ImageHandler $imageHandler */
                $imageHandler = app('xe.media')->getHandler(Media::TYPE_IMAGE);

                // todo: 모바일 판단여부 적용 or 무시 (ex. app('request')->isMobile())
                if ($imageHandler->isAvailable($mime)) {
                    $manager = new ImageManager();
                    $image = $manager->make($uploaded);

                    if (isset($image->exif()['Orientation'])) {
                        $content = $image->orientate()->encode()->getEncoded();

                        file_put_contents($uploaded->getPathname(), $content);

                        $uploaded = new UploadedFile(
                            $uploaded->getPathname(),
                            $uploaded->getClientOriginalName(),
                            $uploaded->getClientMimeType(),
                            strlen($content)
                        );
                    }
                }
            }

            return $target($uploaded, $path, $name, $disk, $user);

        });
    }

    public function activate($installedVersion = null)
    {
        if (!function_exists('exif_read_data')) {
            throw new \Intervention\Image\Exception\NotSupportedException(
                "Reading Exif data is not supported by this PHP installation."
            );
        }
    }
}
