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
 *
 * @author Charles Rouillon <charles.rouillon@lp-digital.fr>
 */

namespace BackBee\Bundle\CropBundle\Repository;

use JMS\Serializer\Annotation as Serializer;
use Doctrine\ORM\Mapping as ORM;
/**
 * crop repository.
 *
 * @category    BackBee
 * @package     BackBee\Bundle\CropBundle
 * @copyright   Lp digital system
 * @author      Mihai Faget <mihai.faget@lp-digital.fr>
 * @ORM\Entity(repositoryClass="BackBee\Bundle\CropBundle\Repository\CropRepository")
 *
 */
class CropRepo
{
	/**
     * Unique identifier of the content.
     *
     * @var string
     * @ORM\Id
     * @ORM\Column(type="string", length=32, name="uid")
     *
     */
    protected $_uid;

    /**
     * Class constructor.
     *
     * @param string $uid The unique identifier
     */
    public function __construct($uid = null)
    {
        parent::__construct($uid);

        $this->_content = new ArrayCollection();
    }
}