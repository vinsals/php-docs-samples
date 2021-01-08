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
declare(strict_types=1);

namespace Google\Cloud\Samples\Functions\ImageMagick\Test;

use Google\CloudFunctions\CloudEvent;

trait TestCasesTrait
{
    public static function getDataForFile($fileName): array
    {
        return [
            'bucket' => getenv('FUNCTIONS_BUCKET'),
            'metageneration' => '1',
            'name' => $fileName,
            'timeCreated' => '2020-04-23T07:38:57.230Z',
            'updated' => '2020-04-23T07:38:57.230Z',
            'statusCode' => '200'
        ];
    }



    public static function cases(): array
    {
        $START_BUCKET_NAME = getenv('FUNCTIONS_BUCKET');
        $BLURRED_BUCKET_NAME = getenv('BLURRED_BUCKET_NAME');

        return [
            [
                'cloudevent' => CloudEvent::fromArray([
                    'id' => uniqid(),
                    'source' => 'storage.googleapis.com',
                    'specversion' => '1.0',
                    'type' => 'google.cloud.storage.object.v1.finalized',
                    'data' => TestCasesTrait::getDataForFile('puppies.jpg'),
                ]),
                'label' => 'Ignores safe images',
                'fileName' => 'puppies.jpg',
                'expected' => 'Detected puppies.jpg as OK',
                'statusCode' => '200'
            ],
            [
                'cloudevent' => CloudEvent::fromArray([
                    'id' => uniqid(),
                    'source' => 'storage.googleapis.com',
                    'specversion' => '1.0',
                    'type' => 'google.cloud.storage.object.v1.finalized',
                    'data' => TestCasesTrait::getDataForFile('zombie.jpg'),
                ]),
                'label' => 'Blurs offensive images',
                'fileName' => 'zombie.jpg',
                'expected' => sprintf(
                    'Streamed blurred image to: gs://%s/zombie.jpg',
                    $BLURRED_BUCKET_NAME
                ),
                'statusCode' => '200'
            ],
        ];
    }

    public static function integrationCases(): array
    {
        $START_BUCKET_NAME = getenv('FUNCTIONS_BUCKET');
        $BLURRED_BUCKET_NAME = getenv('BLURRED_BUCKET_NAME');

        return [
            [
                'cloudevent' => CloudEvent::fromArray([
                    'id' => uniqid(),
                    'source' => 'storage.googleapis.com',
                    'specversion' => '1.0',
                    'type' => 'google.cloud.storage.object.v1.finalized',
                    'data' => TestCasesTrait::getDataForFile('does-not-exist.jpg')
                ]),
                'label' => 'Labels missing images as safe',
                'filename' => 'does-not-exist.jpg',
                'expected' => sprintf(
                    'Could not find gs://%s/does-not-exist.jpg',
                    $START_BUCKET_NAME
                ),
                'statusCode' => '200'
            ],
        ];
    }
}