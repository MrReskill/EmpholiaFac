<?php

namespace FactionsPro;

use FactionsPro\entity\CoeurDeFaction;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\entity\Entity;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\level\Position;

class FactionCommands {

    public $plugin;

    public function __construct(FactionMain $pg) {
        $this->plugin = $pg;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (strtolower($command->getName()) !== "f" || empty($args)) {
            $sender->sendMessage($this->plugin->formatMessage("Utilise /f help pour voir la liste des commandes"));
            return true;
        }
        if (strtolower($args[0]) == "help") {
            $sender->sendMessage(TextFormat::RED . "\n/f about\n/f accept\n\n/f claim\n/f create <name>\n/f del\n/f demote <player>\n/f deny");
            $sender->sendMessage(TextFormat::RED . "\n/f home\n/f help <page>\n/f info\n/f info <faction>\n/f invite <player>\n/f kick <player>\n/f leader <player>\n/f leave");
            $sender->sendMessage(TextFormat::RED . "\n/f sethome\n/f unclaim\n/f unsethome\n/f ourmembers - {Members + Statuses}\n/f ourofficers - {Officers + Statuses}\n/f ourleader - {Leader + Status}\n/f allies - {The allies of your faction");
            $sender->sendMessage(TextFormat::RED . "\n/f desc\n/f promote <player>\n/f allywith <faction>\n/f breakalliancewith <faction>\n\n/f allyok [Accept a request for alliance]\n/f allyno\n/f allies <faction>");
            $sender->sendMessage(TextFormat::RED . "\n/f membersof <faction>\n/f officersof <faction>\n/f leaderof <faction>\n/f say <send message to everyone in your faction>\n/f pf <player>\n/f topfactions");
            $sender->sendMessage(TextFormat::RED . "\n/f forceunclaim <faction> [Unclaim a faction plot by force - OP]\n\n/f forcedelete <faction> [Delete a faction by force - OP]");
            $sender->sendMessage(TextFormat::RED . "\n/f cdf [Placer le coeur de faction]");
            return true;
        }
        if (!$sender instanceof Player || ($sender->isOp() && $this->plugin->prefs->get("AllowOpToChangeFactionPower"))) {
            if (strtolower($args[0]) == "addpdf") {
                if (!isset($args[1]) || !isset($args[2]) || !$this->alphanum($args[1]) || !is_numeric($args[2])) {
                    $sender->sendMessage($this->plugin->formatMessage("Utilise: /f addpdf <faction name> <power>"));
                    return true;
                }
                if ($this->plugin->factionExists($args[1])) {
                    $this->plugin->addFactionPower($args[1], $args[2]);
                    $sender->sendMessage($this->plugin->formatMessage($args[2] . " point de faction ajouté a la faction " . $args[1]));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("La faction " . $args[1] . " n'existe pas"));
                }
            }
            if (!$sender instanceof Player) return true;
        }
        $playerName = $sender->getPlayer()->getName();

            ///////////////////////////////// WAR /////////////////////////////////

            if ($args[0] == "war") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /f war <faction name:tp>"));
                    return true;
                }
                if (strtolower($args[1]) == "tp") {
                    foreach ($this->plugin->wars as $r => $f) {
                        $fac = $this->plugin->getPlayerFaction($playerName);
                        if ($r == $fac) {
                            $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                            $tper = $this->plugin->war_players[$f][$x];
                            $sender->teleport($this->plugin->getServer()->getPlayer($tper));
                            return true;
                        }
                        if ($f == $fac) {
                            $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                            $tper = $this->plugin->war_players[$r][$x];
                            $sender->teleport($this->plugin->getServer()->getPlayer($tper));
                            return true;
                        }
                    }
                    $sender->sendMessage(FactionMain::$prefix . "§cVous devez être en guerre pour faire cela.");
                    return true;
                }
                if (!($this->alphanum($args[1]))) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous ne pouvez utilisez que des lettres et des chiffres."));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Cette faction n'existe pas"));
                    return true;
                }
                if (!$this->plugin->isInFaction($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez avoir une faction."));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être Chef pour lancer une guerre."));
                    return true;
                }
                if (!$this->plugin->areEnemies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Votre faction est désormais ennemie avec $args[1]"));
                    return true;
                } else {
                    $factionName = $args[1];
                    $sFaction = $this->plugin->getPlayerFaction($playerName);
                    foreach ($this->plugin->war_req as $r => $f) {
                        if ($r == $args[1] && $f == $sFaction) {
                            foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                $task = new FactionWar($this->plugin, $r);
                                $handler = $this->plugin->getScheduler()->scheduleDelayedTask($task, 20 * 60 * 2);
                                $task->setHandler($handler);
                                $p->sendMessage(FactionMain::$prefix ."La guerre entre $factionName et $sFaction est déclarée !");
                                if ($this->plugin->getPlayerFaction($p->getName()) == $sFaction) {
                                    $this->plugin->war_players[$sFaction][] = $p->getName();
                                }
                                if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                                    $this->plugin->war_players[$factionName][] = $p->getName();
                                }
                            }
                            $this->plugin->wars[$factionName] = $sFaction;
                            unset($this->plugin->war_req[strtolower($args[1])]);
                            return true;
                        }
                    }
                    $this->plugin->war_req[$sFaction] = $factionName;
                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                        if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                            if ($this->plugin->getLeader($factionName) == $p->getName()) {
                                $p->sendMessage(FactionMain::$prefix ."$sFaction veut commencer une guerre, '/f war $sFaction' pour valider!");
                                $sender->sendMessage(FactionMain::$prefix ."Demande de guerre.");
                                return true;
                            }
                        }
                    }
                    $sender->sendMessage(FactionMain::$prefix ."§CLe Chef de la faction n'est pas en ligne");
                    return true;
                }
            }

            /////////////////////////////// CREATE ///////////////////////////////

            if ($args[0] == "create") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Utilise: /f create <faction name>"));
                    return true;
                }
                if (!($this->alphanum($args[1]))) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous ne pouvez utilisé que des lettres et des chiffres"));
                    return true;
                }
                if ($this->plugin->isNameBanned($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Le nom est interdit"));
                    return true;
                }
                if ($this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Cette faction existe déjà"));
                    return true;
                }
                if (strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
                    $sender->sendMessage($this->plugin->formatMessage("Le nom est trop long"));
                    return true;
                }
                if ($this->plugin->isInFaction($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous avez déjà une faction"));
                    return true;
                } else {
                    $factionName = $args[1];
                    $rank = "Leader";
                    $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                    $stmt->bindValue(":player", $playerName);
                    $stmt->bindValue(":faction", $factionName);
                    $stmt->bindValue(":rank", $rank);
                    $result = $stmt->execute();
                    $this->plugin->updateAllies($factionName);
                    $this->plugin->setFactionPower($factionName, $this->plugin->prefs->get("TheDefaultPowerEveryFactionStartsWith"));
                    $this->plugin->updateTag($sender->getName());
                    $sender->sendMessage($this->plugin->formatMessage("Faction créer !", true));
                    Server::getInstance()->broadcastMessage("§e- §r§bEmpholia §e- §r§f La faction §a". $factionName . "§f viens d'etre crée par §a$playerName !");
                    return true;
                }
            }

            /////////////////////////////// INVITE ///////////////////////////////

            if ($args[0] == "invite") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /f invite <player>"));
                    return true;
                }
                if ($this->plugin->isFactionFull($this->plugin->getPlayerFaction($playerName))) {
                    $sender->sendMessage($this->plugin->formatMessage("La faction est complète."));
                    return true;
                }
                $invited = $this->plugin->getServer()->getPlayerExact($args[1]);
                if (!($invited instanceof Player)) {
                    $sender->sendMessage($this->plugin->formatMessage("Le joueur n'est pas en ligne"));
                    return true;
                }
                if ($this->plugin->isInFaction($invited->getName()) == true) {
                    $sender->sendMessage($this->plugin->formatMessage("Le joueur est dans une faction"));
                    return true;
                }
                if ($this->plugin->prefs->get("OnlyLeadersAndOfficersCanInvite")) {
                    if (!($this->plugin->isOfficer($playerName) || $this->plugin->isLeader($playerName))) {
                        $sender->sendMessage($this->plugin->formatMessage("Seulement les Chefs et les Officiers peuvent inviter."));
                        return true;
                    }
                }
                if ($invited->getName() == $playerName) {

                    $sender->sendMessage($this->plugin->formatMessage("Vous ne pouvez pas vous inviter vous même dans votre faction."));
                    return true;
                }

                $factionName = $this->plugin->getPlayerFaction($playerName);
                $invitedName = $invited->getName();
                $rank = "Member";

                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
                $stmt->bindValue(":player", $invitedName);
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":invitedby", $sender->getName());
                $stmt->bindValue(":timestamp", time());
                $result = $stmt->execute();
                $sender->sendMessage($this->plugin->formatMessage("$invitedName as bien été invité.", true));
                $invited->sendMessage($this->plugin->formatMessage("Vous avez été invité à rejoindre $factionName. Utilise '/f accept' ou '/f deny' dans le chat.", true));
            }

            /////////////////////////////// LEADER ///////////////////////////////

            if ($args[0] == "leader") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /f leader <player>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être dans une faction."));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être Chef"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Le joueur n'est pas dans votre faction"));
                    return true;
                }
                if (!($this->plugin->getServer()->getPlayerExact($args[1]) instanceof Player)) {
                    $sender->sendMessage($this->plugin->formatMessage("Le joueur n'est pas en ligne"));
                    return true;
                }
                if ($args[1] == $sender->getName()) {

                    $sender->sendMessage($this->plugin->formatMessage("Vous ne pouvez pas vous transmettre le grade de Chef à vous même."));
                    return true;
                }
                $factionName = $this->plugin->getPlayerFaction($playerName);

                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                $stmt->bindValue(":player", $playerName);
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":rank", "Member");
                $result = $stmt->execute();

                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                $stmt->bindValue(":player", $args[1]);
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":rank", "Leader");
                $result = $stmt->execute();


                $sender->sendMessage($this->plugin->formatMessage("Vous n'êtes plus le Chef.", true));
                $this->plugin->getServer()->getPlayerExact($args[1])->sendMessage($this->plugin->formatMessage("Vous êtes désormais le Chef \nde $factionName!", true));
                $this->plugin->updateTag($sender->getName());
                $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
            }

            /////////////////////////////// PROMOTE ///////////////////////////////

            if ($args[0] == "promote") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Utilise: /f promote <player>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être dans une faction."));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être Chef"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Le joueur n'est pas dans votre faction"));
                    return true;
                }
                $promotee = $this->plugin->getServer()->getPlayerExact($args[1]);
                if ($promotee instanceof Player && $promotee->getName() == $sender->getName()) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous ne pouvez pas vous promote vous même"));
                    return true;
                }

                if ($this->plugin->isOfficer($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Le joueur est déjà officier"));
                    return true;
                }
                $factionName = $this->plugin->getPlayerFaction($playerName);
                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                $stmt->bindValue(":player", $args[1]);
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":rank", "Officer");
                $result = $stmt->execute();
                $sender->sendMessage($this->plugin->formatMessage("$args[1] est désormais officier", true));

                if ($promotee instanceof Player) {
                    $promotee->sendMessage($this->plugin->formatMessage("Vous êtes désormais officer de $factionName!", true));
                    $this->plugin->updateTag($promotee->getName());
                    return true;
                }
            }

            /////////////////////////////// DEMOTE ///////////////////////////////

            if ($args[0] == "demote") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Utilise: /f demote <player>"));
                    return true;
                }
                if ($this->plugin->isInFaction($sender->getName()) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez avoir une faction"));
                    return true;
                }
                if ($this->plugin->isLeader($playerName) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être chef"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Le joueur n'est pas dans cette faction"));
                    return true;
                }

                if ($args[1] == $sender->getName()) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous ne pouvez pas demote vous même"));
                    return true;
                }
                if (!$this->plugin->isOfficer($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Le joueur est déjà un joueur."));
                    return true;
                }
                $factionName = $this->plugin->getPlayerFaction($playerName);
                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                $stmt->bindValue(":player", $args[1]);
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":rank", "Member");
                $result = $stmt->execute();
                $demotee = $this->plugin->getServer()->getPlayerExact($args[1]);
                $sender->sendMessage($this->plugin->formatMessage("$args[1] le joueur est dépromus", true));
                if ($demotee instanceof Player) {
                    $demotee->sendMessage($this->plugin->formatMessage("You were demoted to member of $factionName!", true));
                    $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                    return true;
                }
            }

            /////////////////////////////// KICK ///////////////////////////////

            if ($args[0] == "kick") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /f kick <player>"));
                    return true;
                }
                if ($this->plugin->isInFaction($sender->getName()) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez avoir une faction"));
                    return true;
                }
                if ($this->plugin->isLeader($playerName) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être chef"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Le joueur n'est pas dans votre faction"));
                    return true;
                }
                if ($args[1] == $sender->getName()) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous ne pouvez pas vous expulser"));
                    return true;
                }
                $kicked = $this->plugin->getServer()->getPlayerExact($args[1]);
                $factionName = $this->plugin->getPlayerFaction($playerName);
                $stmt = $this->plugin->db->prepare("DELETE FROM master WHERE player = :playername;");
                $stmt->bindvalue(":playername", $args[1]);
                $stmt->execute();

                $sender->sendMessage($this->plugin->formatMessage("Vous avez kick $args[1]", true));
                $this->plugin->subtractFactionPower($factionName, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));

                if ($kicked instanceof Player) {
                    $kicked->sendMessage($this->plugin->formatMessage("Vous êtes exclu de \n $factionName", true));
                    $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                    return true;
                }
            }

             /////////////////////////////// COEUR DE FACTION ///////////////////////////////
            if (strtolower($args[0]) == 'cdf') {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être dans une faction"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être leader"));
                    return true;
                }
                $factionName = $this->plugin->getFaction($playerName);

                if($this->plugin->haveCdf($factionName))
                {
                    $sender->sendMessage(
                        $this->plugin->formatMessage("Vous avez déjà un coeur de faction.")
                    );
                } else {
                    $level = $sender->getLevel()->getName();
                    $x = floor($sender->getX());
                    $y = floor($sender->getY());
                    $z = floor($sender->getZ());

                    $this->plugin->addCdf($factionName, $x, $y, $z, $level);
                    $sender->sendMessage(
                        $this->plugin->formatMessage("Vous avez placé le coeur de faction.", true)
                    );

                    $nbt = Entity::createBaseNBT($sender->getPlayer());
                    $crystal = Entity::createEntity("EnderCrystal", $sender->getLevel(), $nbt);
                    $crystal->setNameTag("\n§bCoeur de faction\n§fNiveau 1");
                    $crystal->setScoreTag("§c150 ♥");
                    $crystal->setNameTagAlwaysVisible(true);
                    if($crystal instanceof CoeurDeFaction) $crystal->spawnToAll();
                }

            }

            /////////////////////////////// CLAIM ///////////////////////////////

            if (strtolower($args[0]) == 'claim') {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être dans une faction"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être leader"));
                    return true;
                }
                if (!in_array($sender->getPlayer()->getLevel()->getName(), $this->plugin->prefs->get("ClaimWorlds"))) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous ne pouvez pas claim dans ce monde."));
                    return true;
                }

                if ($this->plugin->inOwnPlot($sender)) {
                    $sender->sendMessage($this->plugin->formatMessage("Votre faction a déjà claim cette zone"));
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());


                $x = floor($sender->getX());
                $y = floor($sender->getY());
                $z = floor($sender->getZ());
                if ($this->plugin->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize")) == false) {
                    return true;
                }

                $sender->sendMessage($this->plugin->formatMessage("Lecture des coordonnées...", true));
                $plot_size = $this->plugin->prefs->get("PlotSize");
                $faction_power = $this->plugin->getFactionPower($faction);
                $sender->sendMessage($this->plugin->formatMessage("La zone vous appartient :)", true));
            }
            if (strtolower($args[0]) == 'plotinfo') {
                $x = floor($sender->getX());
                $y = floor($sender->getY());
                $z = floor($sender->getZ());
                if (!$this->plugin->isInPlot($sender)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous pouvez claim cette zone avec la commande /f claim", true));
                    return true;
                }

                $fac = $this->plugin->factionFromPoint($x, $z, $sender->getPlayer()->getLevel()->getName());
                $power = $this->plugin->getFactionPower($fac);
                $sender->sendMessage($this->plugin->formatMessage("Cette zone est claim par $fac "));
            }
            if (strtolower($args[0]) == 'topfactions') {
                $this->plugin->sendListOfTop10FactionsTo($sender);
            }
            if (strtolower($args[0]) == 'forcedelete') {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Utilise: /f forcedelete <faction>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Cette faction n'existe pas"));
                    return true;
                }
                if (!($sender->isOp())) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous n'avez pas la permission d'executer cette commande"));
                    return true;
                }
                $this->plugin->db->query("DELETE FROM master WHERE faction='$args[1]';");
                $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                $this->plugin->db->query("DELETE FROM allies WHERE faction1='$args[1]';");
                $this->plugin->db->query("DELETE FROM allies WHERE faction2='$args[1]';");
                $this->plugin->db->query("DELETE FROM strength WHERE faction='$args[1]';");
                $this->plugin->db->query("DELETE FROM motd WHERE faction='$args[1]';");
                $this->plugin->db->query("DELETE FROM home WHERE faction='$args[1]';");
                $sender->sendMessage($this->plugin->formatMessage("Done.", true));
            }
            if (strtolower($args[0]) == 'addstrto') {
                if (!isset($args[1]) or !isset($args[2])) {
                    $sender->sendMessage($this->plugin->formatMessage("Utilise: /f addstrto <faction> <STR>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("La faction n'existe pas"));
                    return true;
                }
                if (!($sender->isOp())) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être OP"));
                    return true;
                }
                $this->plugin->addFactionPower($args[1], $args[2]);
                $sender->sendMessage($this->plugin->formatMessage("Done", true));
            }
            if (strtolower($args[0]) == 'pf') {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /f pf <player>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($args[1])) {
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($args[1]);
                $sender->sendMessage($this->plugin->formatMessage("-$args[1] est dans $faction-", true));
            }



            /////////////////////////////// UNCLAIM ///////////////////////////////

            if (strtolower($args[0]) == "unclaim") {
                if (!$this->plugin->isInFaction($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être dans une faction"));
                    return true;
                }
                if (!$this->plugin->isLeader($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être chef"));
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($sender->getName());
                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
                $sender->sendMessage($this->plugin->formatMessage("La zone a été unclaim", true));
            }

            /////////////////////////////// DESCRIPTION ///////////////////////////////

            if (strtolower($args[0]) == "desc") {
                if ($this->plugin->isInFaction($sender->getName()) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être dans une faction"));
                    return true;
                }
                if ($this->plugin->isLeader($playerName) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être chef"));
                    return true;
                }
                $sender->sendMessage($this->plugin->formatMessage("Quelle description voulez vous utilisé ? (Envoyer la comme un message normal)", true));
                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
                $stmt->bindValue(":player", $sender->getName());
                $stmt->bindValue(":timestamp", time());
                $result = $stmt->execute();
            }

            /////////////////////////////// ACCEPT ///////////////////////////////

            if (strtolower($args[0]) == "accept") {
                $lowercaseName = strtolower($playerName);
                $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                $array = $result->fetchArray(SQLITE3_ASSOC);
                if (empty($array) == true) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous n'avez été inviter par aucune faction"));
                    return true;
                }
                $invitedTime = $array["timestamp"];
                $currentTime = time();
                if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                    $faction = $array["faction"];
                    $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                    $stmt->bindValue(":player", ($playerName));
                    $stmt->bindValue(":faction", $faction);
                    $stmt->bindValue(":rank", "Member");
                    $result = $stmt->execute();
                    $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                    $sender->sendMessage($this->plugin->formatMessage("Vous avez rejoint $faction", true));
                    $this->plugin->addFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
                    $inviter = $this->plugin->getServer()->getPlayerExact($array["invitedby"]);
                    if ($inviter !== null) $inviter->sendMessage($this->plugin->formatMessage("$playerName est désormais dans notre faction", true));
                    $this->plugin->updateTag($sender->getName());
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("L'invitation a expîrée"));
                    $this->plugin->db->query("DELETE FROM confirm WHERE player='$playerName';");
                }
            }

            /////////////////////////////// DENY ///////////////////////////////

            if (strtolower($args[0]) == "deny") {
                $lowercaseName = strtolower($playerName);
                $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                $array = $result->fetchArray(SQLITE3_ASSOC);
                if (empty($array) == true) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous n'avez pas été invité"));
                    return true;
                }
                $invitedTime = $array["timestamp"];
                $currentTime = time();
                if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                    $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                    $sender->sendMessage($this->plugin->formatMessage("Invitation annulée", true));
                    $this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$playerName declined the invitation"));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Invitation expirée"));
                    $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                }
            }

            /////////////////////////////// DELETE ///////////////////////////////

            if (strtolower($args[0]) == "del") {
                if ($this->plugin->isInFaction($playerName) == true) {
                    if ($this->plugin->isLeader($playerName)) {
                        $faction = $this->plugin->getPlayerFaction($playerName);
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
                        $this->plugin->db->query("DELETE FROM master WHERE faction='$faction';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction1='$faction';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction2='$faction';");
                        $this->plugin->db->query("DELETE FROM strength WHERE faction='$faction';");
                        $this->plugin->db->query("DELETE FROM motd WHERE faction='$faction';");
                        $this->plugin->db->query("DELETE FROM home WHERE faction='$faction';");
                        $sender->sendMessage($this->plugin->formatMessage("Faction détruite avec succès", true));
                        $this->plugin->updateTag($sender->getName());
                        unset($this->plugin->factionChatActive[$playerName]);
                        unset($this->plugin->allyChatActive[$playerName]);
                    } else {
                        $sender->sendMessage($this->plugin->formatMessage("Vous n'êtes pas chef"));
                    }
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez avoir une faction"));
                }
            }

            /////////////////////////////// LEAVE ///////////////////////////////

            if (strtolower($args[0] == "leave")) {
                if ($this->plugin->isLeader($playerName) == false) {
                    $remove = $sender->getPlayer()->getNameTag();
                    $faction = $this->plugin->getPlayerFaction($playerName);
                    $name = $sender->getName();
                    $this->plugin->db->query("DELETE FROM master WHERE player='$name';");
                    $sender->sendMessage($this->plugin->formatMessage("Vous n'êtes plus dans $faction", true));
                    $this->plugin->subtractFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
                    $this->plugin->updateTag($sender->getName());
                    unset($this->plugin->factionChatActive[$playerName]);
                    unset($this->plugin->allyChatActive[$playerName]);
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez supprimer la faction ou donner le leadership à un autre joueur."));
                }
            }

            /////////////////////////////// SETHOME ///////////////////////////////

            if (strtolower($args[0] == "sethome")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être dans une faction"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous n'êtes pas chef"));
                    return true;
                }
                $factionName = $this->plugin->getPlayerFaction($sender->getName());
                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO home (faction, x, y, z, world) VALUES (:faction, :x, :y, :z, :world);");
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":x", $sender->getX());
                $stmt->bindValue(":y", $sender->getY());
                $stmt->bindValue(":z", $sender->getZ());
                $stmt->bindValue(":world", $sender->getLevel()->getName());
                $result = $stmt->execute();
                $sender->sendMessage($this->plugin->formatMessage("Done.", true));
            }

            /////////////////////////////// UNSETHOME ///////////////////////////////

            if (strtolower($args[0] == "unsethome")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être dans une faction"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être chef"));
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($sender->getName());
                $this->plugin->db->query("DELETE FROM home WHERE faction = '$faction';");
                $sender->sendMessage($this->plugin->formatMessage("Home unset", true));
            }

            /////////////////////////////// HOME ///////////////////////////////

            if (strtolower($args[0] == "home")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être dans une faction"));
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($sender->getName());
                $result = $this->plugin->db->query("SELECT * FROM home WHERE faction = '$faction';");
                $array = $result->fetchArray(SQLITE3_ASSOC);
                if (!empty($array)) {
                    if ($array['world'] === null || $array['world'] === "") {
                        $sender->sendMessage($this->plugin->formatMessage("Votre home de faction n'est plus disponible. Vous devez le déplacé"));
                        return true;
                    }
                    if (Server::getInstance()->loadLevel($array['world']) === false) {
                        return true;
                    }
                    $level = Server::getInstance()->getLevelByName($array['world']);
                    $sender->getPlayer()->teleport(new Position($array['x'], $array['y'], $array['z'], $level));
                    $sender->sendMessage($this->plugin->formatMessage("Téléportation vers votre home", true));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Vous n'avez aucun home"));
                }
            }

            /////////////////////////////// MEMBERS/OFFICERS/LEADER AND THEIR STATUSES ///////////////////////////////
            if (strtolower($args[0] == "ourmembers")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez avoir une faction"));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($playerName), "Member");
            }
            if (strtolower($args[0] == "membersof")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /f membersof <faction>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("La faction n'existe pas"));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Member");
            }
            if (strtolower($args[0] == "ourofficers")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez avoir une faction"));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($playerName), "Officer");
            }
            if (strtolower($args[0] == "officersof")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /f officersof <faction>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("La faction n'existe pas"));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Officer");
            }
            if (strtolower($args[0] == "ourleader")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez avoir une faction"));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($playerName), "Leader");
            }
            if (strtolower($args[0] == "leaderof")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /f leaderof <faction>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("La faction n'existe pas"));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Leader");
            }
            if (strtolower($args[0] == "say")) {
                if (true) {
                    $sender->sendMessage($this->plugin->formatMessage("/f say est interdit"));
                    return true;
                }
                if (!($this->plugin->isInFaction($playerName))) {

                    $sender->sendMessage($this->plugin->formatMessage("Vous devez avoir une faction"));
                    return true;
                }
                $r = count($args);
                $row = array();
                $rank = "";
                $f = $this->plugin->getPlayerFaction($playerName);

                if ($this->plugin->isOfficer($playerName)) {
                    $rank = "*";
                } else if ($this->plugin->isLeader($playerName)) {
                    $rank = "**";
                }
                $message = "-> ";
                for ($i = 0; $i < $r - 1; $i = $i + 1) {
                    $message = $message . $args[$i + 1] . " ";
                }
                $result = $this->plugin->db->query("SELECT * FROM master WHERE faction='$f';");
                for ($i = 0; $resultArr = $result->fetchArray(SQLITE3_ASSOC); $i = $i + 1) {
                    $row[$i]['player'] = $resultArr['player'];
                    $p = $this->plugin->getServer()->getPlayerExact($row[$i]['player']);
                    if ($p instanceof Player) {
                        $p->sendMessage(TextFormat::ITALIC . TextFormat::RED . "<FM>" . TextFormat::AQUA . " <$rank$f> " . TextFormat::GREEN . "<$playerName> " . ": " . TextFormat::RESET);
                        $p->sendMessage(TextFormat::ITALIC . TextFormat::DARK_AQUA . $message . TextFormat::RESET);
                    }
                }
            }


            ////////////////////////////// ALLY SYSTEM ////////////////////////////////
            if (strtolower($args[0] == "enemywith")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /f enemywith <faction>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez avoir une faction"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être chef de la faction"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("La faction n'existe pas"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) == $args[1]) {
                    return true;
                }
                if ($this->plugin->areAllies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Votre faction est désormais alliée avec $args[1]"));
                    return true;
                }
                if ($this->plugin->areEnemies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Votre faction est désormais un ennemie de $args[1]"));
                    return true;
                }
                $fac = $this->plugin->getPlayerFaction($playerName);
                $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));

                if (!($leader instanceof Player)) {
                    $sender->sendMessage($this->plugin->formatMessage("Le chef de leurs faction est hors ligne"));
                } else {
                    $leader->sendMessage($this->plugin->formatMessage("Le chef de $fac vous a déclaré comme étant des ennemies", true));
                }
                $this->plugin->setEnemies($fac, $args[1]);
                $sender->sendMessage($this->plugin->formatMessage("Vous êtes ennemie avec $args[1]!", true));
            }
            if (strtolower($args[0] == "notenemy")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /f notenemy <faction>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez avoir une faction"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être chef"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("La faction requise n'existe pas"));
                    return true;
                }
                $fac = $this->plugin->getPlayerFaction($playerName);
                $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                $this->plugin->unsetEnemies($fac, $args[1]);
                if (!($leader instanceof Player)) {
                    $sender->sendMessage($this->plugin->formatMessage("Le chef de leurs faction est hors ligne"));
                } else {
                    $leader->sendMessage($this->plugin->formatMessage("Le chef de $fac déclare la paix entre vos deux factions", true));
                }
                $sender->sendMessage($this->plugin->formatMessage("Vous n'êtes plus ennemie avec $args[1]!", true));
            }
            if (strtolower($args[0] == "allywith")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /f allywith <faction>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être allié"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être chef"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("La faction n'existe pas"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) == $args[1]) {
                    return true;
                }
                if ($this->plugin->areAllies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Votre faction est désormais allié avec $args[1]"));
                    return true;
                }
                $fac = $this->plugin->getPlayerFaction($playerName);
                $leaderName = $this->plugin->getLeader($args[1]);
                if (!isset($fac) || !isset($leaderName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Faction not found"));
                    return true;
                }
                $leader = $this->plugin->getServer()->getPlayerExact($leaderName);
                $this->plugin->updateAllies($fac);
                $this->plugin->updateAllies($args[1]);
                if (!($leader instanceof Player)) {
                    $sender->sendMessage($this->plugin->formatMessage("Le chef de leurs faction est hors ligne"));
                    return true;
                }
                if ($this->plugin->getAlliesCount($args[1]) >= $this->plugin->getAlliesLimit()) {
                    $sender->sendMessage($this->plugin->formatMessage("The requested faction has the maximum amount of allies", false));
                    return true;
                }
                if ($this->plugin->getAlliesCount($fac) >= $this->plugin->getAlliesLimit()) {
                    $sender->sendMessage($this->plugin->formatMessage("Your faction has the maximum amount of allies", false));
                    return true;
                }
                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO alliance (player, faction, requestedby, timestamp) VALUES (:player, :faction, :requestedby, :timestamp);");
                $stmt->bindValue(":player", $leader->getName());
                $stmt->bindValue(":faction", $args[1]);
                $stmt->bindValue(":requestedby", $sender->getName());
                $stmt->bindValue(":timestamp", time());
                $result = $stmt->execute();
                $sender->sendMessage($this->plugin->formatMessage("Demande d'alliance avec $args[1]!\nEn attente d'une réponse...", true));
                $leader->sendMessage($this->plugin->formatMessage("Le chef de $fac vous propose de faire une alliance.\nUtilise /f allyok pour accepter ou /f allyno pour renoncer", true));
            }
            if (strtolower($args[0] == "breakalliancewith") or strtolower($args[0] == "notally")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /f breakalliancewith <faction>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être dans une faction"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être chef"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("La faction n'existe pas"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) == $args[1]) {
                    return true;
                }
                if (!$this->plugin->areAllies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Votre faction est désormais allié avec $args[1]"));
                    return true;
                }

                $fac = $this->plugin->getPlayerFaction($playerName);
                $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                $this->plugin->deleteAllies($fac, $args[1]);
                $this->plugin->subtractFactionPower($fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                $this->plugin->subtractFactionPower($args[1], $this->plugin->prefs->get("PowerGainedPerAlly"));
                $this->plugin->updateAllies($fac);
                $this->plugin->updateAllies($args[1]);
                $sender->sendMessage($this->plugin->formatMessage("Votre faction $fac n'est plus allié avec $args[1]", true));
                if ($leader instanceof Player) {
                    $leader->sendMessage($this->plugin->formatMessage("Le chef de $fac as renoncer à votre alliance avec $args[1]", false));
                }
            }
            if (strtolower($args[0] == "forceunclaim")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /f forceunclaim <faction>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("La faction n'existe pas"));
                    return true;
                }
                if (!($sender->isOp())) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être opérateur"));
                    return true;
                }
                $sender->sendMessage($this->plugin->formatMessage("Done $args[1]"));
                $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
            }

            if (strtolower($args[0] == "allies")) {
                if (!isset($args[1])) {
                    if (!$this->plugin->isInFaction($playerName)) {
                        $sender->sendMessage($this->plugin->formatMessage("Vous devez être dans une faction pour faire cela."));
                        return true;
                    }

                    $this->plugin->updateAllies($this->plugin->getPlayerFaction($playerName));
                    $this->plugin->getAllAllies($sender, $this->plugin->getPlayerFaction($playerName));
                } else {
                    if (!$this->plugin->factionExists($args[1])) {
                        $sender->sendMessage($this->plugin->formatMessage("La faction n'existe pas"));
                        return true;
                    }
                    $this->plugin->updateAllies($args[1]);
                    $this->plugin->getAllAllies($sender, $args[1]);
                }
            }
            if (strtolower($args[0] == "allyok")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez avoir une faction"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être chef"));
                    return true;
                }
                $lowercaseName = strtolower($playerName);
                $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                $array = $result->fetchArray(SQLITE3_ASSOC);
                if (empty($array) == true) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous n'avez pas d'invitation"));
                    return true;
                }
                $allyTime = $array["timestamp"];
                $currentTime = time();
                if (($currentTime - $allyTime) <= 60) { //This should be configurable
                    $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                    $sender_fac = $this->plugin->getPlayerFaction($playerName);
                    $this->plugin->setAllies($requested_fac, $sender_fac);
                    $this->plugin->addFactionPower($sender_fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                    $this->plugin->addFactionPower($requested_fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                    $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                    $this->plugin->updateAllies($requested_fac);
                    $this->plugin->updateAllies($sender_fac);
                    $this->plugin->unsetEnemies($requested_fac, $sender_fac);
                    $sender->sendMessage($this->plugin->formatMessage("Votre faction est désormais allié avec $requested_fac", true));
                    $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$playerName de $sender_fac as accepter votre alliance!", true));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("La requète à expirer"));
                    $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                }
            }
            if (strtolower($args[0]) == "allyno") {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être dans une faction"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Vous devez être chef"));
                    return true;
                }
                $lowercaseName = strtolower($playerName);
                $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                $array = $result->fetchArray(SQLITE3_ASSOC);
                if (empty($array) == true) {
                    $sender->sendMessage($this->plugin->formatMessage("Aucune requète."));
                    return true;
                }
                $allyTime = $array["timestamp"];
                $currentTime = time();
                if (($currentTime - $allyTime) <= 60) { //This should be configurable
                    $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                    $sender_fac = $this->plugin->getPlayerFaction($playerName);
                    $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                    $sender->sendMessage($this->plugin->formatMessage("Votre faction à refuser l'alliance", true));
                    $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$playerName de $sender_fac a refuser votre alliance"));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("La requète a expirer"));
                    $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                }
            }


            /////////////////////////////// ABOUT ///////////////////////////////

            if (strtolower($args[0] == 'about')) {
                $sender->sendMessage(TextFormat::GREEN . "==========================================\n FactionsPro v1.3.2 by " . TextFormat::BOLD . "Tethered_\nLa version modifiée d'Empholia a été développer par AlbanWeill http://github.com/AlbanW twitter.com/AlbanWeill");
            }
            ////////////////////////////// CHAT ////////////////////////////////
            if (strtolower($args[0]) == "chat" or strtolower($args[0]) == "c") {

                if (!$this->plugin->prefs->get("AllowChat")) {
                    $sender->sendMessage($this->plugin->formatMessage("Cette fonctionnalitée est désactivée", false));
                    return true;
                }

                if ($this->plugin->isInFaction($playerName)) {
                    if (isset($this->plugin->factionChatActive[$playerName])) {
                        unset($this->plugin->factionChatActive[$playerName]);
                        $sender->sendMessage($this->plugin->formatMessage("Vous parlez désormais dans le chat public", false));
                        return true;
                    } else {
                        $this->plugin->factionChatActive[$playerName] = 1;
                        $sender->sendMessage($this->plugin->formatMessage("§aVous parlez désormais dans le chat de votre faction", false));
                        return true;
                    }
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Vous n'êtes dans aucune faction"));
                    return true;
                }
            }
            if (strtolower($args[0]) == "allychat" or strtolower($args[0]) == "ac") {

                if (!$this->plugin->prefs->get("AllowChat")) {
                    $sender->sendMessage($this->plugin->formatMessage("Fonctionnalitée désactivée", false));
                    return true;
                }

                if ($this->plugin->isInFaction($playerName)) {
                    if (isset($this->plugin->allyChatActive[$playerName])) {
                        unset($this->plugin->allyChatActive[$playerName]);
                        $sender->sendMessage($this->plugin->formatMessage("Vous parlez dans le chat public", false));
                        return true;
                    } else {
                        $this->plugin->allyChatActive[$playerName] = 1;
                        $sender->sendMessage($this->plugin->formatMessage("Vous parlez dans le chat des alliés", false));
                        return true;
                    }
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Vous n'êtes dans aucune faction"));
                    return true;
                }
            }

            /////////////////////////////// INFO ///////////////////////////////

            if (strtolower($args[0]) == 'info') {
                if (isset($args[1])) {
                    if (!(ctype_alnum($args[1])) or !($this->plugin->factionExists($args[1]))) {
                        $sender->sendMessage($this->plugin->formatMessage("La faction n'existe pas"));
                        return true;
                    }
                    $faction = $args[1];
                    $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
                    $array = $result->fetchArray(SQLITE3_ASSOC);
                    $power = $this->plugin->getFactionPower($faction);
                    $message = $array["message"];
                    $leader = $this->plugin->getLeader($faction);
                    $online = "";
                    $offline = "";
                    foreach($this->plugin->getFactionMembers($faction) as $str)
                    {
                        if(Server::getInstance()->getPlayer($str) != null)
                        {
                            $online = $online . $str . " ";
                        } else {
                            $offline = $offline . $str . " ";
                        }
                    }

                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "------- EMPHOLIA -------" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "> Faction: " . TextFormat::GREEN . "$faction" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "> Chef: " . TextFormat::YELLOW . "$leader" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "> Joueurs: \n" . "§6- Online: §a$online\n§6- Offline: §c$offline");
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "> Strength: " . TextFormat::RED . "$power" . " STR" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "> Description: " . TextFormat::AQUA . TextFormat::UNDERLINE . "$message" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "-------INFORMATION-------" . TextFormat::RESET);
                } else {
                    if (!$this->plugin->isInFaction($playerName)) {
                        $sender->sendMessage($this->plugin->formatMessage("Vous devez avoir une faction"));
                        return true;
                    }
                    $faction = $this->plugin->getPlayerFaction(($sender->getName()));
                    $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
                    $array = $result->fetchArray(SQLITE3_ASSOC);
                    $power = $this->plugin->getFactionPower($faction);
                    $message = $array["message"];
                    $leader = $this->plugin->getLeader($faction);
                    $numPlayers = $this->plugin->getNumberOfPlayers($faction);
                    $online = "";
                    $offline = "";
                    foreach($this->plugin->getFactionMembers($faction) as $str)
                    {
                        if(Server::getInstance()->getPlayer($str) != null)
                        {
                            $online = $online . $str . " ";
                        } else {
                            $offline = $offline . $str . " ";
                        }
                    }
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "------- EMPHOLIA -------" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "> Faction: " . TextFormat::GREEN . "$faction" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "> Chef: " . TextFormat::YELLOW . "$leader" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "> Joueurs: \n" . "§6- Online: §a$online\n§6- Offline: §c$offline");
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "> Strength: " . TextFormat::RED . "$power" . " STR" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "> Description: " . TextFormat::AQUA . TextFormat::UNDERLINE . "$message" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "-------INFORMATION-------" . TextFormat::RESET);
                }
                return true;
            }
            if ($this->plugin->prefs->get("EnableMap") && (strtolower($args[0]) == "map" or strtolower($args[0]) == "m")) {
                $factionPlots = $this->plugin->getNearbyPlots($sender);
                if ($factionPlots == null) {
                    $sender->sendMessage(TextFormat::RED . "Aucune faction proche de vous");
                    return true;
                }
                $playerFaction = $this->plugin->getPlayerFaction(($sender->getName()));
                $found = false;
                foreach ($factionPlots as $key => $faction) {
                    $plotFaction = $factionPlots[$key]['faction'];
                    if ($plotFaction == $playerFaction) {
                        continue;
                    }
                    if ($this->plugin->isInPlot($sender)) {
                        $inWhichPlot = $this->plugin->factionFromPoint($sender->getX(), $sender->getZ(), $sender->getLevel()->getName());
                        if ($inWhichPlot == $plotFaction) {
                            $sender->sendMessage(TextFormat::GREEN . "Vous êtes dans la zone de la faction " . $plotFaction);
                            $found = true;
                            continue;
                        }
                    }
                    $found = true;
                    $x1 = $factionPlots[$key]['x1'];
                    $x2 = $factionPlots[$key]['x2'];
                    $z1 = $factionPlots[$key]['z1'];
                    $z2 = $factionPlots[$key]['z2'];
                    $plotX = $x1 + ($x2 - $x1) / 2;
                    $plotZ = $z1 + ($z2 - $z1) / 2;
                    $deltaX = $plotX - $sender->getX();
                    $deltaZ = $plotZ - $sender->getZ();
                    $bearing = rad2deg(atan2($deltaZ, $deltaX));
                    if ($bearing >= -22.5 && $bearing < 22.5) $direction = "south";
                    else if ($bearing >= 22.5 && $bearing < 67.5) $direction = "southwest";
                    else if ($bearing >= 67.5 && $bearing < 112.5) $direction = "west";
                    else if ($bearing >= 112.5 && $bearing < 157.5) $direction = "northwest";
                    else if ($bearing >= 157.5) $direction = "north";
                    else if ($bearing < -22.5 && $bearing > -67.5) $direction = "southeast";
                    else if ($bearing <= -67.5 && $bearing > -112.5) $direction = "east";
                    else if ($bearing <= -112.5 && $bearing > -157.5) $direction = "northeast";
                    else if ($bearing <= -157.5) $direction = "north";
                    $distance = floor(sqrt(pow($deltaX, 2) + pow($deltaZ, 2)));
                    $sender->sendMessage(TextFormat::GREEN ."Une zone de la faction" . $plotFaction . "' est à " . $distance . " blocks " . $direction);
                }
                if (!$found) {
                    $sender->sendMessage(TextFormat::RED . "Aucune faction proche de vous");
                } else {
                    $points = ["south", "west", "north", "east"];
                    $sender->sendMessage(TextFormat::YELLOW . "Vous regardez " . $points[$sender->getDirection()]);
                }
            }
        return true;
    }

    public function alphanum($string) {
        if (function_exists('ctype_alnum')) {
            $return = ctype_alnum($string);
        } else {
            $return = preg_match('/^[a-z0-9]+$/i', $string) > 0;
        }
        return $return;
    }
}
