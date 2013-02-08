<?php

namespace ETS\Payment\OgoneBundle\Tests\Client;

use ETS\Payment\OgoneBundle\Client\Token;

/*
 * Copyright 2013 ETSGlobal <e4-devteam@etsglobal.org>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Token class test
 *
 * @author ETSGlobal <e4-devteam@etsglobal.org>
 */
class TokenTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test the object construction
     */
    public function testConstruct()
    {
        $pspid = 'foobar';
        $password = 'foopass';
        $shain = 'fooshain';
        $shaout= 'fooshaout';

        $token = new Token($pspid, $password, $shain, $shaout);

        $this->assertEquals($pspid, $token->getPspid());
        $this->assertEquals($password, $token->getPassword());
        $this->assertEquals($shain, $token->getShain());
        $this->assertEquals($shaout, $token->getShaout());
    }
}