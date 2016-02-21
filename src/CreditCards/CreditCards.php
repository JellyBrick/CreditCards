<?php

namespace CreditCards;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as Color;
use pocketmine\utils\Utils;
use pocketmine\command\PluginCommand;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use onebone\economyapi\EconomyAPI;
use pocketmine\Player;
use pocketmine\Server;

class CreditCards extends PluginBase implements Listener {
	public $config, $data;
	public $money;
	public $limit;
	public $limit_temp;
	public $limit_check;
	public $overdue;
	public $month;
	
	public function onEnable() {
		$data = $this->data;
		@mkdir ( $this->getDataFolder () );
		$this->config = new Config ( $this->getDataFolder () . "CreditCards.yml", Config::YAML, [ 
				"Limit" => 100000,
				"Month" => $this->months(),
				"Cards" => [ ] 
		] );
		$this->data = $this->config->getAll ();
		if (! ($this->money = $this->getServer ()->getPluginManager ()->getPlugin ( "EconomyAPI" )) and ! ($this->money = $this->getServer ()->getPluginManager ()->getPlugin ( "EconomyAPI" ))) {
			$this->getLogger ()->info ( Color::RED . "EconomyAPI 플러그인이 없습니다... 플러그인을 비활성화 합니다" );
			$this->getServer ()->getPluginManager ()->disablePlugin ( $this );
		} else {
			$this->getLogger ()->info ( Color::BLUE . "EconomyAPI 플러그인을 감지했습니다...! 플러그인을 활성화 합니다!" );
		}
		$this->monthDate();
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		$this->api = EconomyAPI::getInstance ();
		// $this->messages = $this->MessageLoad();
	}
	public function saveYml() {
		$save = new Config ( $this->getDataFolder () . "CreditCards.yml", Config::YAML );
		$save->setAll ( $this->data );
		$save->save ();
	} /*
	   * public function MessageLoad()
	   * {
	   * $this->saveResource("messages.yml");
	   * return (new Config($this->getDataFolder()."messages.yml", Config::YAML))->getAll();
	   * }
	   */
	public function months()
	{
		date ( "n" );
	}
	public function monthDate()
	{
		$overdue = $this->data ["Cards"] [$name] ["Overdue"];
		$month = $data["Month"];
		$mine = $this->$data["Cards"] [$player->getName ()];
		$check_overdue = $data["Cards"] [$mine] ["Overdue"];
		$check_limitover = $data["Cards"] [$mine] ["Current_payments"];
		if (date ( "n" ) != $month) {
			if ($check_overdue=0) {
				$data ["Cards"] [$mine] = [ 
					"Current_payments" => 0,
					"Overdue" => 0,
				];
			}
			else {
				$data ["Cards"] [$mine] = [
					"Current_payments" => 0,
					"Overdue" => $overdue,
				];
			}
		}
	}
	public function onDisable() {
		$this->saveYml ();
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		$data = $this->data;
		$prefix = "[ 서버 ]";
		$limit = $this->data ["Limit"];
		
		$server = Server::getInstance ();
		$player = array_shift ( $args );
		$amount = array_shift ( $args );
		$p = $server->getPlayer ( $player );
		$name = strtolower ( $sender->getName () );
		if ($p instanceof Player) {
			$player = $p->getName ();
		}
		$result = $this->api->addMoney ( $player, $amount );
		switch ($command->getName ()) {
			case "신용결제" :
				if (! isset ( $args [0] )) {
					$sender->sendMessage ( Color::RED . "$prefix /신용결제 <돈을 줄 닉네임> <돈의 양>" );
					$sender->sendMessage ( Color::RED . "$prefix <>는 빼고 입력해주세요!" );
					return true;
				}
				$Current_payments = $this->data ["Cards"] [$name] ["Current_payments"];
				$limit_check = $amount + $Current_payments;
				$limit_temp = $limit_check;
				$overdue = $this->data ["Cards"] [$name] ["Overdue"];
				switch ($result) {
					case -2 :
						$sender->sendMessage ( Color::RED . "$prefix 오류로 인해 승인이 취소되었습니다!" );
						break;
					case -1 :
						$sender->sendMessage ( Color::RED . "$prefix $player 님은 서버에 접속한 적이 없습니다." );
						break;
					case $limit_check > $limit :
						$sender->sendMessage ( Color::RED . "$prefix 한도를 초과하여 결제 할수 없습니다" );
						break;
					case $overdue > 1 :
						$sender->sendMessage ( Color::RED . "$prefix 카드가 연체 되어 있어 사용이 불가능 합니다!" );
						break;
					case 1 :
						$sender->sendMessage ( Color::GOLD . "$prefix $player 님에게 신용카드로 $amount 만큼 결제 하였습니다!" );
						$name = strtolower ( $sender->getName () );
						$data ["Cards"] [$name] = [ 
								"Current_payments" => $Current_payments + $amount,
								"Overdue" => $overdue,
						];
						$sendername = $sender->getName ();
						if ($p instanceof Player) {
							$p->sendMessage ( Color::BLUE . "$prefix  $sendername 님이 신용카드로 $amount 만큼 결제하였습니다!" );
						}
						break;
				}
			case "신용" :
				switch ($args [0])
				{
					case "결제금액" :
						$sender->sendMessage ( Color::GREEN . "$prefix 여태까지 결제한 금액은 $Current_payments 입니다!" );
					case "도움말" :
						foreach ( $this->getUserHelper () as $help ) {
							$sender->sendMessage ( Color::DARK_GREEN . "$prefix $help" );
						}
				}
		}
	}
	public function getUserHelper() {
		$arr = [
				"/신용결제 <닉네임> <돈 양>",
				"/신용 결제금액"
		];
		return $arr;
	}
}
?>