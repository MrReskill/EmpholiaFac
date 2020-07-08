<?php
namespace FactionsPro;


use pocketmine\scheduler\Task;

class FactionClock extends Task
{
    /**
     * @var FactionMain
     */
    private $loader;

    public function __construct(FactionMain $main)
    {
        $this->loader = $main;
    }


    public function onRun(int $currentTick)
    {
        foreach ($this->loader->getServer()->getOnlinePlayers() as $p)
        {
            $x = $p->getFloorX();
            $z = $p->getFloorZ();
            $level = $p->getLevel();

            if($this->loader->isInPlot($p))
            {
                $p->sendPopup("§f- §3Vous êtes dans le claim de §b".$this->loader->factionFromPoint($x, $z, $level->getName()) . " §f-");
            }
        }
    }
}