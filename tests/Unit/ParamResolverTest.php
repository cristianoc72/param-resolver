<?php declare(strict_types=1);
/*
 * Copyright (c) Cristiano Cinotti 2024.
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *  http://www.apache.org/licenses/LICENSE-2.0
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

use Susina\ParamResolver\Exception\ParamResolverException;
use Susina\ParamResolver\ParamResolver;

beforeEach(function () {
    $this->resolver = new ParamResolver();
});

it('resolves parameters', function () {
    putenv('host=127.0.0.1');
    putenv('user=root');

    $config = [
        'HoMe' => 'myHome',
        'project' => 'myProject',
        'subhome' => '%HoMe%/subhome',
        'property1' => 1,
        'property2' => false,
        'directories' => [
            'project' => '%HoMe%/projects/%project%',
            'conf' => '%project%',
            'schema' => '%project%/schema',
            'template' => '%HoMe%/templates',
            'output%project%' => '/build',
        ],
        '%HoMe%' => 4,
        'host' => '%env.host%',
        'user' => '%env.user%',
    ];

    $expected = [
        'HoMe' => 'myHome',
        'project' => 'myProject',
        'subhome' => 'myHome/subhome',
        'property1' => 1,
        'property2' => false,
        'directories' => [
            'project' => 'myHome/projects/myProject',
            'conf' => 'myProject',
            'schema' => 'myProject/schema',
            'template' => 'myHome/templates',
            'outputmyProject' => '/build',
        ],
        'myHome' => 4,
        'host' => '127.0.0.1',
        'user' => 'root',
    ];

    expect($expected)->toBe($this->resolver->resolve($config));

    //cleanup environment
    putenv('host');
    putenv('user');
});

it('resolves parameters via static instantiation', function () {
    putenv('host=127.0.0.1');
    putenv('user=root');

    $config = [
        'HoMe' => 'myHome',
        'project' => 'myProject',
        'subhome' => '%HoMe%/subhome',
        'property1' => 1,
        'property2' => false,
        'directories' => [
            'project' => '%HoMe%/projects/%project%',
            'conf' => '%project%',
            'schema' => '%project%/schema',
            'template' => '%HoMe%/templates',
            'output%project%' => '/build',
        ],
        '%HoMe%' => 4,
        'host' => '%env.host%',
        'user' => '%env.user%',
    ];

    $expected = [
        'HoMe' => 'myHome',
        'project' => 'myProject',
        'subhome' => 'myHome/subhome',
        'property1' => 1,
        'property2' => false,
        'directories' => [
            'project' => 'myHome/projects/myProject',
            'conf' => 'myProject',
            'schema' => 'myProject/schema',
            'template' => 'myHome/templates',
            'outputmyProject' => '/build',
        ],
        'myHome' => 4,
        'host' => '127.0.0.1',
        'user' => 'root',
    ];

    expect($expected)->toBe(ParamResolver::create()->resolve($config));

    //cleanup environment
    putenv('host');
    putenv('user');
});


it('resolves values', function (array $conf, array $expected) {
    expect($expected)->toBe($this->resolver->resolve($conf));
})->with('resolveParams');

it('does not cast to strigs the replaced values', function () {
    $conf = $this->resolver->resolve(['foo' => true, 'expfoo' => '%foo%', 'bar' => null, 'expbar' => '%bar%']);

    expect($conf['expfoo'])->toBeTrue()->and($conf['expbar'])->toBeNull();
});

it('finds invalid placeholders', fn () => $this->resolver->resolve(['foo' => 'bar', '%baz%']))
    ->throws(ParamResolverException::class, "Parameter 'baz' not found.");

it('finds not existent placeholder', fn () => $this->resolver->resolve(['foo %foobar% bar']))
    ->throws(ParamResolverException::class, "Parameter 'foobar' not found.");

it('discovers simple circular reference', fn () => $this->resolver->resolve(['foo' => '%bar%', 'bar' => '%foobar%', 'foobar' => '%foo%']))
    ->throws(ParamResolverException::class, "Circular reference detected for parameter 'bar'.");

it('discovers complex circular reference', fn () => $this->resolver->resolve(['foo' => 'a %bar%', 'bar' => 'a %foobar%', 'foobar' => 'a %foo%']))
    ->throws(ParamResolverException::class, "Circular reference detected for parameter 'bar'.");

it('resolves environment variable parameters', function () {
    putenv('home=myHome');
    putenv('schema=mySchema');
    putenv('isBoolean=true');
    putenv('integer=1');

    $config = [
        'home' => '%env.home%',
        'property1' => '%env.integer%',
        'property2' => '%env.isBoolean%',
        'direcories' => [
            'projects' => '%home%/projects',
            'schema' => '%env.schema%',
            'template' => '%home%/templates',
            'output%env.home%' => '/build',
        ],
    ];

    $expected = [
        'home' => 'myHome',
        'property1' => '1',
        'property2' => 'true',
        'direcories' => [
            'projects' => 'myHome/projects',
            'schema' => 'mySchema',
            'template' => 'myHome/templates',
            'outputmyHome' => '/build',
        ],
    ];

    expect($this->resolver->resolve($config))->toBe($expected);

    //cleanup environment
    putenv('home');
    putenv('schema');
    putenv('isBoolean');
    putenv('integer');
});

it('resolves empty environment variable', function () {
    putenv('home=');

    $config = [
        'home' => '%env.home%',
    ];

    $expected = [
        'home' => '',
    ];

    expect($expected)->toBe($this->resolver->resolve($config));

    //cleanup environment
    putenv('home');
});

it('finds not existent environment variable', function () {
    putenv('home=myHome');

    $config = [
        'home' => '%env.home%',
        'property1' => '%env.foo%',
    ];

    $this->resolver->resolve($config);
})->throws(ParamResolverException::class, "Environment variable 'foo' is not defined.");

it('finds non string or number parameters', function () {
    $config = [
        'foo' => 'a %bar%',
        'bar' => [],
        'baz' => '%foo%',
    ];

    $this->resolver->resolve($config);
})->throws(ParamResolverException::class, 'A string value must be composed of strings and/or numbers.');

it('resolve a param twice', function () {
    $config = [
        'foo' => 'bar',
        'baz' => '%foo%',
    ];

    expect(['foo' => 'bar', 'baz' => 'bar'])->toBe($this->resolver->resolve($config))
        ->and([])->toBe($this->resolver->resolve($config));
});
