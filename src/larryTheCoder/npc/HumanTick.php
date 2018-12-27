<?php
/**
 * Adapted from the Wizardry License
 *
 * Copyright (c) 2015-2018 larryTheCoder and contributors
 *
 * Permission is hereby granted to any persons and/or organizations
 * using this software to copy, modify, merge, publish, and distribute it.
 * Said persons and/or organizations are not allowed to use the software or
 * any derivatives of the work for commercial use or any other means to generate
 * income, nor are they allowed to claim this software as their own.
 *
 * The persons and/or organizations are also disallowed from sub-licensing
 * and/or trademarking this software without explicit permission from larryTheCoder.
 *
 * Any persons and/or organizations using this software must disclose their
 * source code and have it publicly available, include this license,
 * provide sufficient credit to the original authors of the project (IE: larryTheCoder),
 * as well as provide a link to the original project.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,FITNESS FOR A PARTICULAR
 * PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
 * USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace larryTheCoder\npc;

use larryTheCoder\SkyWarsPE;
use pocketmine\entity\Skin;
use pocketmine\nbt\tag\StringTag;
use pocketmine\scheduler\Task;
use pocketmine\Server;

class HumanTick extends Task {

	/** @var FakeHuman */
	private $entity;
	private $tickSkin = 10;
	private $levelPedestal;

	public function __construct(FakeHuman $entity){
		$this->entity = $entity;
		$this->levelPedestal = $entity->levelPedestal;
	}

	/**
	 * Actions to execute when run
	 *
	 * @param int $currentTick
	 *
	 * @return void
	 */
	public function onRun(int $currentTick){
		if(is_null($this->entity->getLevel())){
			return;
		}
		// Look at the player, and sent the packet only
		// to the player who looked at it
		foreach($this->entity->getLevel()->getPlayers() as $p){
			if($p->distance($this->entity) <= 5){
				$this->entity->lookAtInto($p);
			}
		}

		// Then send this player skins to the players.
		// Tick every 2 seconds
		if($this->tickSkin >= 10){
			$db = SkyWarsPE::getInstance()->getDatabase()->getPlayers();
			// Avoid nulls and other consequences
			$player = []; // PlayerName => Kills
			$player["Example-1"] = 0;
			$player["Example-2"] = 0;
			$player["Example-3"] = 0;
			foreach($db as $value){
				$player[$value->player] = $value->wins;
			}

			arsort($player);

			// Limit them to 3
			$limit = 0;
			foreach($player as $p => $wins){
				if($limit >= 3){
					continue;
				}

				// Send the skin
				if(Server::getInstance()->getPlayer($p) === null
					&& file_exists(Server::getInstance()->getDataPath() . "players/" . strtolower($p) . ".dat")){
					$nbt = Server::getInstance()->getOfflinePlayerData($p);
					$skin = $nbt->getCompoundTag("Skin");
					if($skin !== \null){
						$skin = new Skin(
							$skin->getString("Name"),
							$skin->hasTag("Data", StringTag::class) ? $skin->getString("Data") : $skin->getByteArray("Data"), //old data (this used to be saved as a StringTag in older versions of PM)
							$skin->getByteArray("CapeData", ""),
							$skin->getString("GeometryName", ""),
							$skin->getByteArray("GeometryData", "")
						);
						$this->entity->setSkin($skin);
						$this->entity->sendSkin();
					}
				}else{
					$player = Server::getInstance()->getPlayer($p);
					if($player !== null){
						$this->entity->setSkin(Server::getInstance()->getPlayer($p)->getSkin());
						$this->entity->sendSkin();
					}
				}

				// The text packets
				$msg1 = str_replace(["{PLAYER}", "{VAL}", "{WINS}"], [$p, $this->levelPedestal, $wins], SkyWarsPE::getInstance()->getMsg(null, 'top-winner-1', false));
				$msg2 = str_replace(["{PLAYER}", "{VAL}", "{WINS}"], [$p, $this->levelPedestal, $wins], SkyWarsPE::getInstance()->getMsg(null, 'top-winner-2', false));
				$msg3 = str_replace(["{PLAYER}", "{VAL}", "{WINS}"], [$p, $this->levelPedestal, $wins], SkyWarsPE::getInstance()->getMsg(null, 'top-winner-3', false));
				$array = [$msg1, $msg2, $msg3];
				$this->entity->sendText($array);

				$limit++;
			}
			$this->tickSkin = 0;
		}
		$this->tickSkin++;
	}
}