<?php

    /*
     * This file is part of the Ariadne Component Library.
     *
     * (c) Muze <info@muze.nl>
     *
     * For the full copyright and license information, please view the LICENSE
     * file that was distributed with this source code.
     */


    class TestNoXSS extends PHPUnit\Framework\TestCase
    {
        function testDetectPrevent()
        {
            $caught = false;
            $_GET['unsafe'] = "This is ' unsafe";
            \arc\noxss::detect();
            echo $_GET['unsafe'];
               \arc\noxss::prevent( function () use (&$caught) {
                $caught = true;
            } );
            $this->assertTrue( $caught );
        }

    }
