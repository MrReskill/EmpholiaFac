<?php
namespace FactionsPro\entity;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Explosion;
use pocketmine\entity\Entity;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;


class CoeurDeFaction extends Entity {


    public const TAG_SHOW_BOTTOM = "ShowBottom";
    public const NETWORK_ID = self::ENDER_CRYSTAL;


    public $height = 0.98;
    public $width = 0.98;


    public function __construct(Level $level, CompoundTag $nbt){
        if(!$nbt->hasTag(self::TAG_SHOW_BOTTOM, ByteTag::class)){
            $nbt->setByte(self::TAG_SHOW_BOTTOM, 0);
        }
        parent::__construct($level, $nbt);
    }


    public function attack(EntityDamageEvent $source): void{
        return;
    }
}

