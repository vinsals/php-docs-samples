<?php
/**
 * Copyright 2020 Google LLC.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// [START functions_imagemagick_setup]
use Google\CloudFunctions\CloudEvent;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;

define('VERY_LIKELY', 5);
// [END functions_imagemagick_setup]

// [START functions_imagemagick_analyze]

function blurOffensiveImages(CloudEvent $cloudevent): void
{
    $log = fopen(getenv('LOGGER_OUTPUT') ?: 'php://stderr', 'wb');
    $storage = new StorageClient();
    $data = $cloudevent->getData();

    $file = $storage->bucket($data['bucket'])->object($data['name']);
    $filePath = 'gs://' . $data['bucket'] . '/' . $data['name'];
    fwrite($log, 'Analyzing ' . $filePath . PHP_EOL);

    $annotator = new ImageAnnotatorClient();
    $storage = new StorageClient();

    try {
        $request = $annotator->safeSearchDetection($filePath);
        $response = $request->getSafeSearchAnnotation();

        // Handle missing files
        // (This is uncommon, but can happen if race conditions occur)
        if ($response === null) {
            fwrite($log, 'Could not find ' . $filePath . PHP_EOL);
            return;
        }

        $is_inappropriate =
            $response->getAdult() === VERY_LIKELY ||
            $response->getViolence() === VERY_LIKELY;

        if ($is_inappropriate) {
            fwrite($log, 'Detected ' . $data['name'] . ' as inappropriate.' . PHP_EOL);
            $BLURRED_BUCKET_NAME = getenv('BLURRED_BUCKET_NAME');

            blurImage($log, $file, $BLURRED_BUCKET_NAME);
        } else {
            fwrite($log, 'Detected ' . $data['name'] . ' as OK.' . PHP_EOL);
        }
    } catch (Exception $e) {
        fwrite($log, 'Failed to analyze ' . $data['name'] . PHP_EOL);
        fwrite($log, $e->getMessage() . PHP_EOL);
    }
}
// [END functions_imagemagick_analyze]

// [START functions_imagemagick_blur]
// Blurs the given file using ImageMagick, and uploads it to another bucket.
function blurImage(
    $log,
    Object $file,
    string $blurredBucketName
): void {
    $tempLocalPath = sys_get_temp_dir() . '/' . $file->name();

    // Download file from bucket.
    try {
        $file->downloadToFile($tempLocalPath);

        fwrite($log, 'Downloaded ' . $file->name() . ' to: ' . $tempLocalPath . PHP_EOL);
    } catch (Exception $e) {
        throw new Exception('File download failed: ' . $e);
    }

    // Blur file using ImageMagick
    // (The Imagick class is from the PECL 'imagick' package)
    $image = new Imagick($tempLocalPath);
    $image->blurImage(0, 16);

    try {
        $image->writeImage($tempLocalPath);

        fwrite($log, 'Blurred image: ' . $file->name() . PHP_EOL);
    } catch (Exception $e) {
        fwrite($log, 'Failed to blur image: ' . $e->getMessage() . PHP_EOL);
    }

    // Upload result to a different bucket, to avoid re-triggering this function.
    $storage = new StorageClient();
    $blurredBucket = $storage->bucket($blurredBucketName);

    // Upload the Blurred image back into the bucket.
    $gcsPath = 'gs://' . $blurredBucketName . '/' . $file->name();
    try {
        $blurredBucket->upload($tempLocalPath, [
            'name' => $file->name()
        ]);
        fwrite($log, 'Uploaded blurred image to: ' . $gcsPath . PHP_EOL);
    } catch (Exception $e) {
        throw new Exception('Unable to upload blurred image to ' . $gcsPath . ': ' . $err);
    }

    // Delete the temporary file.
    unlink($tempLocalPath);
}
// [END functions_imagemagick_blur]
