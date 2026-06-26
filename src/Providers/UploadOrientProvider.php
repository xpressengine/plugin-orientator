<?php

declare(strict_types=1);

namespace Xpressengine\Plugins\Orientator\Providers;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Xpressengine\Plugins\Orientator\Actions\OrientImageFileAction;

/**
 * XeStorage 업로드 흐름에 이미지 방향 보정 Action을 연결한다.
 */
final readonly class UploadOrientProvider
{
    /**
     * 업로드 파일 방향 보정 책임을 Action으로 위임한다.
     */
    public function __construct(
        private OrientImageFileAction $orientImageFileAction
    ) {
    }

    /**
     * XeStorage 업로드 전에 EXIF Orientation 보정을 수행하는 interceptor를 등록한다.
     */
    public function boot(): void
    {
        intercept(
            'XeStorage@upload',
            'orientator.orientate',
            function (
                callable $target,
                UploadedFile $uploaded,
                string $path,
                ?string $name = null,
                ?string $disk = null,
                $user = null,
                $option = [],
                $force = false
            ) {
                return $target(
                    $this->orientImageFileAction->execute($uploaded),
                    $path,
                    $name,
                    $disk,
                    $user,
                    $option,
                    $force
                );
            }
        );
    }
}
