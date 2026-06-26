<?php

declare(strict_types=1);

namespace Xpressengine\Plugins\Orientator\Actions;

use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\BmpEncoder;
use Intervention\Image\Encoders\GifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Xpressengine\Media\MediaManager;
use Xpressengine\Media\Models\Media;

/**
 * 업로드된 이미지 파일의 EXIF Orientation을 보정한다.
 *
 * EXIF Orientation은 이미지 픽셀을 실제로 회전시키는 값이 아니라,
 * 촬영 방향과 표시 방향의 차이를 설명하는 메타데이터다.
 * Intervention Image 3.x에서는 read()가 이 정보를 반영하므로,
 * 업로드 시점에 다시 저장해 두면 표시 결과를 안정적으로 맞출 수 있다.
 */
final readonly class OrientImageFileAction
{
    private const ENCODERS = [
        'image/png' => PngEncoder::class,
        'image/x-png' => PngEncoder::class,
        'image/gif' => GifEncoder::class,
        'image/webp' => WebpEncoder::class,
        'image/bmp' => BmpEncoder::class,
        'image/x-ms-bmp' => BmpEncoder::class,
        'image/avif' => AvifEncoder::class,
    ];

    /**
     * 이미지 처리에 사용할 MediaManager를 주입한다.
     */
    public function __construct(
        private MediaManager $mediaManager
    ) {
    }

    /**
     * 지원 이미지에 Orientation EXIF가 있으면 보정된 이미지로 다시 저장한다.
     */
    public function execute(File $file): File
    {
        // 처리 대상이 아니면 원본을 그대로 반환한다.
        if (!$this->isAvailableImage($file)) {
            return $file;
        }

        $image = $this->readImage($file);

        // EXIF 방향값이 없으면 픽셀을 다시 저장할 필요가 없다.
        if ($this->exifOrientation($image) === null) {
            return $file;
        }

        $content = $this->encodeImage(
            $this->orientImage($image),
            $file->getMimeType()
        );

        file_put_contents($file->getPathname(), $content);

        return $this->rebuildFile($file);
    }

    /**
     * XE 이미지 핸들러가 허용하는 파일만 처리한다.
     */
    private function isAvailableImage(File $file): bool
    {
        // 업로드 실패 파일은 이미지 판별 전에 제외한다.
        if ($file instanceof UploadedFile && !$file->isValid()) {
            return false;
        }

        $mimeType = $file->getMimeType();

        if ($mimeType === null) {
            return false;
        }

        $handler = $this->mediaManager->getHandler(Media::TYPE_IMAGE);

        return $handler->isAvailable($mimeType);
    }

    /**
     * Intervention Image 3.x API로 이미지를 읽는다.
     */
    private function readImage(File $file): ImageInterface
    {
        return $this->imageManager()->read($file->getPathname());
    }

    /**
     * EXIF Orientation 값을 읽는다.
     */
    private function exifOrientation(ImageInterface $image): ?int
    {
        $orientation = $image->exif('IFD0.Orientation');

        return is_int($orientation) ? $orientation : null;
    }

    /**
     * EXIF 방향값에 따라 이미지를 회전한다.
     */
    private function orientImage(ImageInterface $image): ImageInterface
    {
        return $image->orient();
    }

    /**
     * MIME 타입에 맞춰 이미지 바이트 문자열을 만든다.
     */
    private function encodeImage(ImageInterface $image, ?string $mime): string
    {
        // MIME을 알 수 없으면 저장 안전성이 높은 JPEG로 내보낸다.
        if ($mime === null) {
            return $image->encode(new JpegEncoder)->toString();
        }

        $encoderClass = self::ENCODERS[$mime] ?? JpegEncoder::class;

        return $image->encode(new $encoderClass)->toString();
    }

    /**
     * Intervention Image 3.x용 GD manager를 생성한다.
     */
    private function imageManager(): ImageManager
    {
        return ImageManager::gd();
    }

    /**
     * 원본 파일 종류에 맞는 파일 객체로 다시 만든다.
     */
    private function rebuildFile(File $file): File
    {
        // 업로드 파일은 원본 메타를 유지하고, 일반 파일은 경로만 다시 감싼다.
        if ($file instanceof UploadedFile) {
            return $this->rebuildUploadedFile($file);
        }

        return new File($file->getPathname());
    }

    /**
     * UploadedFile의 원본 정보와 업로드 상태를 유지한다.
     */
    private function rebuildUploadedFile(UploadedFile $uploaded): UploadedFile
    {
        return new UploadedFile(
            $uploaded->getPathname(),
            $uploaded->getClientOriginalName(),
            $uploaded->getClientMimeType(),
            $uploaded->getError()
        );
    }
}
