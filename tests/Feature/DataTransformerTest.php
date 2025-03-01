<?php

use Carbon\Carbon;
use Surgiie\Transformer\Contracts\Transformable;
use Surgiie\Transformer\DataTransformer;
use Surgiie\Transformer\Exceptions\NotCallableException;
use Surgiie\Transformer\Transformer;

beforeEach(function () {
    Transformer::unguard();
    $this->data = [
        'first_name' => '    jim    ',
        'last_name' => '   thompson',
        'date_of_birth' => '2020-05-24',
        'password' => 'abcdefgh12345',
        'favorite_number' => '24',
        'favorite_date' => null,
        'get_notifications' => true,
        'contact_info' => [
            'address_one' => '123 some lane street',
            'home_phone' => '1234567890',
            'cell_phone' => '1234567890',
            'apartment_number' => '12',
            'email' => 'email@example.com',
        ],
    ];
});

it('calls functions on data', function () {
    // does nothing when no functions specified.
    $transformer = (new DataTransformer($this->data, []));
    $transformedData = $transformer->transform();
    expect($transformedData)->toBe($this->data);

    // otherwise calls functions.
    $transformer = (new DataTransformer($this->data, [
        'first_name' => 'trim|ucfirst',
        'favorite_number' => 'intval',
    ]));

    $transformedData = $transformer->transform();
    expect($transformedData['first_name'])->toBe('Jim');
    expect($transformedData['favorite_number'])->toBe(24);
    expect($transformedData['first_name'])->not->toBe($this->data['first_name']);
});

it('can use class constants', function () {
    $formatter = (new DataTransformer($this->data, ['date_of_birth' => [
        'trim',
        Carbon::class,
    ]]));

    $data = $formatter->transform();
    expect($data['date_of_birth'])->toBeInstanceOf(Carbon::class);
});

it('throws exception when non callable is called', function () {
    expect(function () {
        $transformer = (new DataTransformer($this->data, ['first_name' => 'im_not_a_callable_function']));
        $transformer->transform();
    })->toThrow(NotCallableException::class);
});

it('can specify value order', function () {
    $formatter = (new DataTransformer($this->data, [
        'password' => 'trim|preg_replace:/[^0-9]/,,:value:',
    ]));

    $formattedData = $formatter->transform();
    expect($formattedData['password'])->toBe('12345');
    expect($formattedData['password'])->not->toBe($this->data['password']);
});

it('can process callbacks', function () {
    $formatter = (new DataTransformer($this->data, [
        'get_notifications' => function () {
            return 'Never';
        },
    ]));

    $formattedData = $formatter->transform();

    expect($formattedData['get_notifications'])->toBe('Never');

    expect($formattedData['get_notifications'])->not->toBe($this->data['get_notifications']);
});

it('can process tranformable objects', function () {
    $formatter = (new DataTransformer($this->data, [
        'get_notifications' => new class() implements Transformable
        {
            public function transform($value, Closure $exit)
            {
                return 'Yes';
            }
        },
    ]));

    $formattedData = $formatter->transform();

    expect($formattedData['get_notifications'])->toBe('Yes');

    expect($formattedData['get_notifications'])->not->toBe($this->data['get_notifications']);
});

it('can exits on blank input using ?', function () {
    $formatter = (new DataTransformer($this->data, [
        'favorite_date' => '?|Carbon\Carbon|.format:m/d/Y',
    ]));

    $formattedData = $formatter->transform();

    expect($formattedData['favorite_date'])->toBe($this->data['favorite_date']);
    expect($formattedData['favorite_date'])->not->toBe((new Carbon())->format('m/d/Y'));

    //assert at any position in the list of functions
    $this->data['favorite_date'] = '2022-05-24';
    $formatter = (new DataTransformer($this->data, [
        'favorite_date' => ['Carbon\Carbon', function () {
            return null;
        }, '?', '.format:m/d/Y'],
    ]));

    $formattedData = $formatter->transform();
    expect($formattedData['favorite_date'])->toBeNull();
    expect($formattedData['favorite_date'])->not->toBe('05/24/2022');
});

it('can delegate to underlying objects', function () {
    $formatter = (new DataTransformer($this->data, [
        'date_of_birth' => 'trim|Carbon\Carbon|->addDays:1|->format:m/d/Y',
    ]));

    $formattedData = $formatter->transform();

    expect($formattedData['date_of_birth'])->not->toBe($this->data['date_of_birth']);
    expect($formattedData['date_of_birth'])->toBe('05/25/2020');
});
