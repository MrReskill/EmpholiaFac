<?php

namespace FactionsPro;

use FactionsPro\entity\CoeurDeFaction;
use pocketmine\command\defaults\KillCommand;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityIds;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityMotionEvent;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\level\format\Chunk;
use pocketmine\level\Position;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerDeathEvent;

class FactionListener implements Listener {

    public $plugin;

    public function __construct(FactionMain $pg) {
        $this->plugin = $pg;
    }

    public function factionChat(PlayerChatEvent $PCE) {

        $player = $PCE->getPlayer()->getName();
        //MOTD Check

        if ($this->plugin->motdWaiting($player)) {
            if (time() - $this->plugin->getMOTDTime($player) > 30) {
                $PCE->getPlayer()->sendMessage(FactionMain::$prefix . "§cRéutilise /f desc..");
                $this->plugin->db->query("DELETE FROM motdrcv WHERE player='$player';");
                $PCE->setCancelled(true);
                return true;
            } else {
                $motd = $PCE->getMessage();
                $faction = $this->plugin->getPlayerFaction($player);
                $this->plugin->setMOTD($faction, $player, $motd);
                $PCE->setCancelled(true);
                $PCE->getPlayer()->sendMessage(FactionMain::$prefix . "§aDes informations de votre faction ont été mise à jour(s).");
            }
            return true;
        }
        if (isset($this->plugin->factionChatActive[$player])) {
            if ($this->plugin->factionChatActive[$player]) {
                $msg = $PCE->getMessage();
                $faction = $this->plugin->getPlayerFaction($player);
                foreach ($this->plugin->getServer()->getOnlinePlayers() as $fP) {
                    if ($this->plugin->getPlayerFaction($fP->getName()) == $faction) {
                        if ($this->plugin->getServer()->getPlayer($fP->getName())) {
                            $PCE->setCancelled(true);
                            $this->plugin->getServer()->getPlayer($fP->getName())->sendMessage(TextFormat::DARK_GREEN . "[$faction]" . TextFormat::BLUE . " $player: " . TextFormat::AQUA . $msg);
                        }
                    }
                }
            }
        }
        if (isset($this->plugin->allyChatActive[$player])) {
            if ($this->plugin->allyChatActive[$player]) {
                $msg = $PCE->getMessage();
                $faction = $this->plugin->getPlayerFaction($player);
                foreach ($this->plugin->getServer()->getOnlinePlayers() as $fP) {
                    if ($this->plugin->areAllies($this->plugin->getPlayerFaction($fP->getName()), $faction)) {
                        if ($this->plugin->getServer()->getPlayer($fP->getName())) {
                            $PCE->setCancelled(true);
                            $this->plugin->getServer()->getPlayer($fP->getName())->sendMessage(TextFormat::DARK_GREEN . "[$faction]" . TextFormat::BLUE . " $player: " . TextFormat::AQUA . $msg);
                            $PCE->getPlayer()->sendMessage(TextFormat::DARK_GREEN . "[$faction]" . TextFormat::BLUE . " $player: " . TextFormat::AQUA . $msg);
                        }
                    }
                }
            }
        }
    }

    public function factionPVP(EntityDamageEvent $factionDamage) {
        if ($factionDamage instanceof EntityDamageByEntityEvent) {
            if (!($factionDamage->getEntity() instanceof Player) or !($factionDamage->getDamager() instanceof Player)) {
                return true;
            }
            if (($this->plugin->isInFaction($factionDamage->getEntity()->getPlayer()->getName()) == false) or ($this->plugin->isInFaction($factionDamage->getDamager()->getPlayer()->getName()) == false)) {
                return true;
            }
            if (($factionDamage->getEntity() instanceof Player) and ($factionDamage->getDamager() instanceof Player)) {
                $player1 = $factionDamage->getEntity()->getPlayer()->getName();
                $player2 = $factionDamage->getDamager()->getPlayer()->getName();
                $f1 = $this->plugin->getPlayerFaction($player1);
                $f2 = $this->plugin->getPlayerFaction($player2);
                if ((!$this->plugin->prefs->get("AllowFactionPvp") && $this->plugin->sameFaction($player1, $player2) == true) or (!$this->plugin->prefs->get("AllowAlliedPvp") && $this->plugin->areAllies($f1, $f2))) {
                    $factionDamage->setCancelled(true);
                }
            }
        }
    }

    public function factionBlockBreakProtect(BlockBreakEvent $event) {
        $x = $event->getBlock()->getX();
        $z = $event->getBlock()->getZ();
        $level = $event->getBlock()->getLevel()->getName();
        if ($this->plugin->pointIsInPlot($x, $z, $level)) {
            if ($this->plugin->factionFromPoint($x, $z, $level) === $this->plugin->getFaction($event->getPlayer()->getName())) {
                return;
            } else {
                $event->setCancelled(true);
                $event->getPlayer()->sendMessage(FactionMain::$prefix . "§cCette zone appartient déjà à une autre faction.");
                return;
            }
        }
    }

    public function factionBlockPlaceProtect(BlockPlaceEvent $event) {
        $x = $event->getBlock()->getX();
        $z = $event->getBlock()->getZ();
        $level = $event->getBlock()->getLevel()->getName();
        if ($this->plugin->pointIsInPlot($x, $z, $level)) {
            if ($this->plugin->factionFromPoint($x, $z, $level) == $this->plugin->getFaction($event->getPlayer()->getName())) {
                return;
            } else {
                $event->setCancelled(true);
                $event->getPlayer()->sendMessage(FactionMain::$prefix . "§cCette zone appartient déjà à une autre faction.");
                return;
            }
        }
    }

    public function onKill(PlayerDeathEvent $event) {
        $ent = $event->getEntity();
        $cause = $event->getEntity()->getLastDamageCause();
        if ($cause instanceof EntityDamageByEntityEvent) {
            $killer = $cause->getDamager();
            if ($killer instanceof Player) {
                $p = $killer->getPlayer()->getName();
                if ($this->plugin->isInFaction($p)) {
                    $f = $this->plugin->getPlayerFaction($p);
                    $e = $this->plugin->prefs->get("PowerGainedPerKillingAnEnemy");
                    if ($ent instanceof Player) {
                        if ($this->plugin->isInFaction($ent->getPlayer()->getName())) {
                            $this->plugin->addFactionPower($f, $e);
                        } else {
                            $this->plugin->addFactionPower($f, $e / 2);
                        }
                    }
                }
            }
        }
        if ($ent instanceof Player) {
            $e = $ent->getPlayer()->getName();
            if ($this->plugin->isInFaction($e)) {
                $f = $this->plugin->getPlayerFaction($e);
                $e = $this->plugin->prefs->get("PowerGainedPerKillingAnEnemy");
                if ($ent->getLastDamageCause() instanceof EntityDamageByEntityEvent && $ent->getLastDamageCause()->getDamager() instanceof Player) {
                    if ($this->plugin->isInFaction($ent->getLastDamageCause()->getDamager()->getPlayer()->getName())) {
                        $this->plugin->subtractFactionPower($f, $e * 2);
                    } else {
                        $this->plugin->subtractFactionPower($f, $e);
                    }
                }
            }
        }
    }

    /**
     * @param EntityDeathEvent $event
     */
    public function onEntityDeath(EntityDamageEvent $event)
    {
        if($event->getEntity() instanceof CoeurDeFaction)
        {
            $event->setCancelled(
                true
            );
        }
    }

    public function onMotion(EntityMotionEvent $event)
    {
        if($event->getEntity() instanceof CoeurDeFaction)
        {
            $event->setCancelled(
                true
            );
        }
    }

    public function onPlayerJoin(PlayerJoinEvent $event) {
        /**
         * $nbt = Entity::createBaseNBT($event->getPlayer()->add(0.5, 0, 0.5));
         * $crystal = Entity::createEntity("EnderCrystal", $event->getPlayer()->getLevel(), $nbt);
         * if($crystal instanceof CoeurDeFaction) $crystal->spawnToAll();
        **/

        $this->plugin->updateTag($event->getPlayer()->getName());
    }
}