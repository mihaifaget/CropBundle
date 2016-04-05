<?php

/*
 * Copyright (c) 2011-2016 Lp digital system
 *
 * This file is part of BackBee.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * BackBee is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee. If not, see <http://www.gnu.org/licenses/>.
 */

namespace BackBee\Bundle\CropBundle;

use BackBee\Bundle\AbstractBundle;

/**
 * CropBundle main class
 *
 * @category    BackBee\Bundle
 * @package     CropBundle
 * @copyright   Lp digital system
 * @author      Mihai Faget <mihai.faget@lp-digital.fr>
 */
class Crop extends AbstractBundle
{
    /**
     * {@inheritdoc}
     */
    public function start()
    {
        //die('aaaaaa');
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        return $this;
    }
}