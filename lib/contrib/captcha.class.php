<?php
/*
    This file is part of WeeWork.

    WeeWork is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    WeeWork is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with WeeWork; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

    $Id$

    Captcha Implementation

    Usage:
    $length = 4;
    $c = new Captcha($length);
    $code = $c->Generate("tmp/captcha.png");
    $global->captcha = $code;
    echo "<img src=\"tmp/captcha.png\">";

 */

class Captcha
{
    var $strCheck = "";
    var $strLength = 3;
    var $img;
    var $colorBg;
    var $colorTxt = array();
    var $colorLine;

    function Captcha($length)
    {
        $this->strLength = $length;
    }

    /* Generates the Image to the file and returns the string to verify */
    function Generate($imgName)
    {
        $this->GenStr();
        $this->img = imageCreate(200,50);
        $this->GenColors();
        $this->PutLetters();
        $this->PutEllipses();
        $this->PutLines();
        imagePNG($this->img, $imgName);
        return $this->strCheck;
    }

    function GenStr()
    {
        $this->strCheck = "";

        for($i=0 ; $i < $this->strLength ; $i++)
        {
            $textornumber = rand(1,3);
            if($textornumber == 1)
            {
                $this->strCheck .= chr(rand(49,57));
            }
            else if($textornumber == 2)
            {
                $this->strCheck .= chr(rand(65,78));
            }
            else if($textornumber == 3)
            {
                $this->strCheck .= chr(rand(80,90));
            }
        }
    }

    function GenColors()
    {
        $colorR = rand(100,230);
        $colorG = rand(100,230);
        $colorB = rand(100,230);

        $colorG2 = (rand(100,230)+$colorG)/2;
        $colorB2 = (rand(100,230)+$colorB)/2;

        $this->colorBg = imageColorAllocate($this->img, $colorR, $colorG, $colorB);
        $this->colorTxt[0] = imageColorAllocate($this->img, ($colorR - 80), ($colorG2 - 70), ($colorB - 80));
        $this->colorTxt[1] = imageColorAllocate($this->img, ($colorR - 70), ($colorG - 80), ($colorB2 - 70));
        $this->colorLine = imageColorAllocate($this->img, ($colorR - 10), ($colorG2 - 20), ($colorB2 - 10));
    }

    function PutLetters()
    {
        $range = (200/($this->strLength+1));
        for($i=0 ; $i < $this->strLength ; $i++)
        {
            $clockorcounter = rand(1,2);
            if($clockorcounter == 1)
            {
                $rotangle = rand(0,45);
            }
            else
            {
                $rotangle = rand(315,360);
            }

            $place = $range*($i+1) + rand(1,$range/2) - rand(1,$range/2);
            imagettftext($this->img, rand(14,20), $rotangle, $place, 30, $this->colorTxt[$i%2],
	    	Swisdk::config_value('captcha.font_path', 'arial.ttf'), substr($this->strCheck, $i, 1) );
        }
    }

    function PutEllipses()
    {
        for($i=0 ; $i<4 ; $i++)
        {
            imageellipse($this->img,rand(1,200),rand(1,50),rand(50,100),rand(12,25),$this->colorLine);
        }
        for($i=0 ; $i<4 ; $i++)
        {
            imageellipse($this->img,rand(1,200),rand(1,50),rand(50,100),rand(12,25),$this->colorLine);
        }
    }

    function PutLines()
    {
        for($i=0 ; $i<8 ; $i++)
        {
            imageline($this->img,rand(1,200),rand(1,50),rand(50,100),rand(12,25),$this->colorLine);
        }
    }
}

