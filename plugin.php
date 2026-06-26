<?php

declare(strict_types=1);

namespace Xpressengine\Plugins\Orientator;

use Illuminate\Contracts\Container\BindingResolutionException;
use Intervention\Image\Exceptions\NotSupportedException;
use Xpressengine\Plugin\AbstractPlugin;
use Xpressengine\Plugins\Orientator\Providers\UploadOrientProvider;

/**
 * 업로드 이미지 방향 보정 플러그인의 진입점입니다.
 *
 * [책임과 범위]
 * - 플러그인 등록을 위한 최소 진입점을 제공합니다.
 * - 실제 업로드 보정 로직은 Provider에서 부팅합니다.
 */
final class Plugin extends AbstractPlugin
{
    /**
     * @var array<int, class-string>
     */
    const PROVIDERS = [
        UploadOrientProvider::class,
    ];

    /**
     * 플러그인 부팅 시 Provider 목록을 순서대로 실행한다.
     *
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $this->bootProviders();
    }

    /**
     * EXIF 정보를 읽을 수 없는 환경에서는 플러그인 활성화를 중단한다.
     */
    public function activate($installedVersion = null): void
    {
        if (!function_exists('exif_read_data')) {
            throw new NotSupportedException(
                'Reading Exif data is not supported by this PHP installation.'
            );
        }
    }

    /**
     * 등록된 Provider 인스턴스를 컨테이너에서 해석하고 boot 메서드를 호출한다.
     *
     * @throws BindingResolutionException
     */
    private function bootProviders(): void
    {
        foreach (self::PROVIDERS as $providerClass) {
            app()->make($providerClass)->boot();
        }
    }
}
