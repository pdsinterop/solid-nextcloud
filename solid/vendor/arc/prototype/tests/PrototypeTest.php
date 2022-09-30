<?php

    /*
     * This file is part of the Ariadne Component Library.
     *
     * (c) Muze <info@muze.nl>
     *
     * For the full copyright and license information, please view the LICENSE
     * file that was distributed with this source code.
     */

    class PrototypeTest extends PHPUnit\Framework\TestCase
    {
        function testPrototype()
        {
            $view = \arc\prototype::create( [
                'foo' => 'bar',
                'bar' => function () {
                    return $this->foo;
                }
            ] );
            $this->assertEquals( $view->foo, 'bar' );
            $this->assertEquals( $view->bar(), 'bar' );
        }

        function testPrototypeInheritance()
        {
            $foo = \arc\prototype::create( [
                'foo' => 'bar',
                'bar' => function () {
                    return $this->foo;
                }
            ]);
            $bar = \arc\prototype::extend( $foo, [
                'foo' => 'rab'
            ]);
            $this->assertEquals( $foo->foo, 'bar' );
            $this->assertEquals( $bar->foo, 'rab' );
            $this->assertEquals( $foo->bar(), 'bar' );
            $this->assertEquals( $bar->bar(), 'rab' );
            $this->assertTrue( \arc\prototype::hasOwnProperty($bar, 'foo') );
            $this->assertFalse( \arc\prototype::hasOwnProperty($bar, 'bar') );
        }

        function testPrototypeInheritance2()
        {
            $foo = \arc\prototype::create([
                'bar' => function () {
                    return 'bar';
                }
            ]);
            $bar = \arc\prototype::extend($foo, [
                'bar' => function () use ($foo) {
                    return 'foo'.$foo->bar();
                }
            ]);
            $this->assertEquals( $bar->bar(), 'foobar' );
        }

        function testPrototypeInheritance3()
        {
            $foo = \arc\prototype::create([
                'bar' => function () {
                    return 'bar';
                },
                'foo' => function () {
                    return '<b>'.$this->bar().'</b>';
                }
            ]);
            $bar = \arc\prototype::extend($foo, [
                'bar' => function () use ($foo) {
                    return 'foo'.$foo->bar();
                }
            ]);
            $this->assertEquals( $bar->foo(), '<b>foobar</b>' );
        }

        function testStatic() 
        {
            $foo = \arc\prototype::create([
                'bar' => 'Bar',
                ':foo' => static function($self) {
                    return $self->bar;
                }
            ]);
            $this->assertEquals( $foo->foo(), 'Bar');
        }

        function testInvoke()
        {
            $foo = \arc\prototype::create([
                '__invoke' => function () {
                    return 'foobar';
                }
            ]);
            $result = $foo();
            $this->assertEquals('foobar', $result);
        }

        function testToString()
        {
            $foo = \arc\prototype::create([
                '__toString' => function() {
                    return 'Foo';
                }
            ]);
            $this->assertEquals('Foo', (string) $foo);
        }
        
        function testStaticToString()
        {            
            $bar = \arc\prototype::create([
                'bar' => 'Bar',
                ':__toString' => static function($self) {
                    return $self->bar;
                }
            ]);
            $this->assertEquals('Bar', $bar.'');
        }

        function testObserve()
        {
            $foo = \arc\prototype::create([]);
			$log = [];
            $f = function($changes) use (&$log) {
				$log[] = $changes;
            };
            \arc\prototype::observe($foo, $f);
            $foo->bar = 'bar';
            $this->assertEquals( 'bar', $log[0]['name']);
            $this->assertEquals( 'add', $log[0]['type']);
			$foo->bar = 'foo';
            $this->assertEquals( 'bar', $log[1]['name']);
            $this->assertEquals( 'update', $log[1]['type']);
            $this->assertEquals( 'bar', $log[1]['oldValue']);
			unset($foo->bar);
            $this->assertEquals( 'bar', $log[2]['name']);
            $this->assertEquals( 'delete', $log[2]['type']);
            $this->assertEquals( 'foo', $log[2]['oldValue']);
        }

        function testFreeze()
        {
            $this->expectException(\LogicException::class);
            $foo = \arc\prototype::create([]);
            \arc\prototype::freeze($foo);
            $foo->bar = 'bar';
            $this->assertArrayNotHasKey('bar', \arc\prototype::entries($foo));
        }

        function testNotExtendable()
        {
            $this->expectException(\LogicException::class);
            $foo = \arc\prototype::create([
                'bar' => 'Bar'
            ]);
            \arc\prototype::preventExtensions($foo);
            $bar = \arc\prototype::extend($foo, [
                'foo' => 'Foo'
            ]);
            $this->assertNull($bar);
        }

        function testAssign()
        {
            $foo = \arc\prototype::create([
                'bar' => 'Bar'
            ]);
            $bar = \arc\prototype::extend($foo, [
                'foo' => 'Foo'
            ]);
            $zod = \arc\prototype::create([
                'zod' => 'Zod'
            ]);
            $zed = \arc\prototype::create([
                'zed' => 'Zed'
            ]);
            $zoom = \arc\prototype::assign($zod, $bar, $zed);
            $this->assertEquals($zoom->bar, $foo->bar);
            $this->assertEquals($zoom->zod, $zod->zod);
        }

        function testGetter()
        {
            $foo = \arc\prototype::create([
                'bar' => [
                    'get' => function() {
                        return 'Bar';
                    }
                ]
            ]);
            $bar = $foo->bar;
            $this->assertEquals('Bar', $bar);
            $this->expectException(\LogicException::class);
            $foo->bar = 'Foo';
            $this->assertEquals('Bar', $bar);
        }

        function testSetter()
        {
            $bar = new StdClass();
            $bar->bar = 'BarBar';
            $foo = \arc\prototype::create([
                'bar' => [
                    'set' => function($value) use ($bar) {
                        $bar->bar = $value.'Bar';
                    },
                    'get' => function() use ($bar) {
                        return $bar->bar;
                    }
                ]
            ]);
            $result = $foo->bar;
            $this->assertEquals('BarBar', $result);
            $foo->bar = 'Foo';
            $result = $foo->bar;
            $this->assertEquals('FooBar', $result);
        }

        function testStaticGetterSetter()
        {
            $bar = new StdClass();
            $bar->bar = 'BarBar';
            $foo = \arc\prototype::create([
                'bar' => [
                    ':set' => static function($self, $value) use ($bar) {
                        $bar->bar = $value.'Bar';
                    },
                    ':get' => static function($self) use ($bar) {
                        return 'Foo'.$bar->bar;
                    }
                ]
            ]);
            $result = $foo->bar;
            $this->assertEquals('FooBarBar', $result);
            $foo->bar = 'Foo';
            $result = $foo->bar;
            $this->assertEquals('FooFooBar', $result);            
        }

        function testIntrospection()
        {
            $foo = \arc\prototype::create( [
                'foo' => 'bar',
                'bar' => function () {
                    return $this->foo;
                }
            ]);
            $bar = \arc\prototype::extend( $foo, [
                'foo' => 'rab'
            ]);
            $this->assertTrue(\arc\prototype::hasPrototype($bar, $foo));
            $prototypes = \arc\prototype::getPrototypes($bar);
            $this->assertEquals($prototypes[0], $foo);
            $instances = \arc\prototype::getInstances($foo);
            $this->assertEquals($instances[0], $bar);
        }

    }
