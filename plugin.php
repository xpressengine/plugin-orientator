<?php
namespace Xpressengine\Plugins\Orientator;

use Xpressengine\Plugin\AbstractPlugin;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Xpressengine\Media\Spec\Media;
use Intervention\Image\ImageManager;

class Plugin extends AbstractPlugin
{
    public function boot()
    {
        intercept('Storage@upload', 'orientator.orientate', function ($target, $uploaded, $path, $name = null, $disk = null) {
            /** @var UploadedFile $uploaded */
            if ($uploaded->isValid()) {
                $mime = $uploaded->getMimeType();

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

            return $target($uploaded, $path, $name, $disk);

        });
    }

    public function activate($installedVersion = null)
    {
        //
    }
}
