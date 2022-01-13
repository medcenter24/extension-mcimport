<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2019 (original work) MedCenter24.com;
 */

namespace medcenter24\McImport\Entities\Importing;


use Carbon\Carbon;
use medcenter24\mcCore\App\Entity\Accident;
use medcenter24\mcCore\App\Entity\AccidentAbstract;
use medcenter24\mcCore\App\Entity\Patient;
use medcenter24\mcCore\App\Entity\User;

/**
 * The state of the case for one operation of importing
 * Class ImportingCase
 * @package medcenter24\McImport\Entities\Importing
 */
class ImportingCase
{
    /**
     * @var Accident
     */
    private $accident;

    /**
     * @var AccidentAbstract
     */
    private $caseable;

    /**
     * @var Patient
     */
    private $patient;

    /**
     * Current create date to use the same time
     * @var string
     */
    private $currentTime;

    /**
     * @var User
     */
    private $user;

    public function setAccident(Accident $accident): void
    {
        $this->accident = $accident;
    }

    public function getAccident(): Accident
    {
        return $this->accident;
    }

    public function hasCaseable(): bool
    {
        return $this->caseable !== null;
    }

    public function setCaseable(AccidentAbstract $caseable): void
    {
        $this->caseable = $caseable;
    }

    public function getCaseable(): AccidentAbstract
    {
        return $this->caseable;
    }

    public function hasPatient(): bool
    {
        return $this->patient !== null;
    }

    public function setPatient(Patient $patient): void
    {
        $this->patient = $patient;
    }

    public function getPatient(): Patient
    {
        return $this->patient;
    }

    public function getCurrentTime(): string
    {
        if (!$this->currentTime) {
            $this->currentTime = \Illuminate\Support\Carbon::now()->format('Y-m-d H:i:s');
        }
        return Carbon::parse($this->currentTime)->format('Y-m-d H:i:s');
    }

    public function setCurrentTime($updatedTime): void
    {
        $this->currentTime = $updatedTime;
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @param User $user
     */
    public function setUser(User $user): void
    {
        $this->user = $user;
    }
}
