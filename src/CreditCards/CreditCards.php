<?php

/*
 * The plugin that allows you to use your credit card in PocketMine-MP.
 * Copyright (C) 2016 JellyBrick_
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace CreditCards;

use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as Color;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use onebone\economyapi\EconomyAPI;
use pocketmine\Player;

class CreditCards extends PluginBase implements Listener
{
    public $config, $data;
    public $money;
    public $api;
    public $prefix;

    public function onEnable()
    {
        @mkdir($this->getDataFolder());
        $this->config = new Config ($this->getDataFolder() . "CreditCards.yml", Config::JSON, [
            "Prefix" => "[ 서버 ] ",
            "Limit" => 100000,
            "Month" => $this->getMonth(),
            "Cards" => []
        ]);
        $this->data = $this->config->getAll();

        $this->prefix = $this->data["Prefix"];

        try {
            $this->api = EconomyAPI::getInstance();
            $this->getLogger()->info(Color::BLUE . "EconomyAPI 플러그인을 감지했습니다! 플러그인을 활성화합니다!");
        } catch (\Exception $e) {
            $this->getLogger()->info(Color::RED . "EconomyAPI 플러그인이 없습니다. 플러그인을 비활성화합니다");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->refreshDate();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function saveYml()
    {
        $save = new Config ($this->getDataFolder() . "CreditCards.yml", Config::YAML);
        $save->setAll($this->data);
        $save->save();
    }

    public function getMonth()
    {
        date("Y/n");
    }

    public function getDate()
    {
        date("Y/n/j");
    }

    public function refreshDate()
    {
        if (($month = $this->getMonth()) !== $this->data ["Month"]) {
            $this->data ["Month"] = $month;
            foreach ($this->data["Cards"] as $playerName) {
                $moneyAmount = $this->data ["Cards"] [$playerName] ["Current_payments"];
                $result = $this->api->reduceMoney($playerName, $moneyAmount);
                switch ($result) {
                    case EconomyAPI::RET_INVALID :
                    case EconomyAPI::RET_NO_ACCOUNT :
                        break;
                    case EconomyAPI::RET_CANCELLED :
                        $this->data ["Cards"] [$playerName] ["Overdue"] = true;
                        break;
                    case EconomyAPI::RET_SUCCESS :
                        $this->data ["Cards"] [$playerName] ["Current_payments"] = 0;
                        $this->data ["Cards"] [$playerName] ["Overdue"] = false;
                        return;
                }
            }
        }
    }

    public function onDisable()
    {
        $this->saveYml();
    }

    public function onPlayerJoin(PlayerJoinEvent $event)
    {
        if (!isset($this->data["Cards"][$name = strtolower($event->getPlayer()->getName())]))
            $this->data["Cards"][$name] = [
                "Current_payments" => 0,
                "Overdue" => false
            ];
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {

        switch ($command->getName()) {
            case "신용결제" :
                $player = array_shift($args);
                $amount = array_shift($args);
                $name = strtolower($sender->getName());
                if (!isset ($args [0])) {
                    $sender->sendMessage(Color::RED . $this->prefix . " /신용결제 <돈을 줄 닉네임> <돈의 양>");
                    $sender->sendMessage(Color::RED . $this->prefix . " <>는 빼고 입력해주세요!");
                    return false;
                }

                $currentPayments = $this->data ["Cards"] [$name] ["Current_payments"];

                if (($addResult = $amount + $currentPayments) > $this->data ["Limit"]) {
                    $sender->sendMessage(Color::RED . $this->prefix . "한도를 초과하여 결제 할수 없습니다");
                    return false;
                } elseif ($this->data ["Cards"] [$name] ["Overdue"]) {
                    $sender->sendMessage(Color::RED . $this->prefix . "카드가 연체 되어 있어 사용이 불가능 합니다!");
                    return false;
                }

                $result = $this->api->addMoney($player, $amount);
                switch ($result) {
                    case EconomyAPI::RET_INVALID :
                        $sender->sendMessage(Color::RED . $this->prefix . "잘못된 숫자입니다.");
                        break;
                    case EconomyAPI::RET_CANCELLED :
                        $sender->sendMessage(Color::RED . $this->prefix . "요청이 거부되었습니다.");
                        break;
                    case EconomyAPI::RET_NO_ACCOUNT :
                        $sender->sendMessage(Color::RED . $this->prefix . "$player 님은 서버에 접속한 적이 없습니다.");
                        break;
                    case EconomyAPI::RET_SUCCESS :
                        $sender->sendMessage(Color::GOLD . $this->prefix . "$player 님에게 신용카드로 $amount 만큼 결제하였습니다!");
                        $name = strtolower($sender->getName());
                        $this->data ["Cards"] [$name] ["Current_payments"] = $addResult;

                        $p = $this->getServer()->getPlayer($player);

                        if ($p !== null || $p instanceof Player) {
                            $p->sendMessage(Color::BLUE . $this->prefix . $sender->getName() . "님이 신용카드로 " . $amount . "만큼 결제하였습니다!");
                        }
                        return true;
                }
                return false;
            case "신용" :
                switch ($args [0]) {
                    case "결제금액" :
                        $sender->sendMessage(Color::GREEN . $this->prefix . "여태까지 결제한 금액은 " . $this->data["Cards"][strtolower($sender->getName())]["Current_payments"] . "입니다.");
                        return true;
                    case "도움말" :
                        foreach ($this->getHelp() as $help) {
                            $sender->sendMessage(Color::DARK_GREEN . $this->prefix . " $help");
                        }
                        return true;
                    case "비용납부" :
                        $playerName = strtolower($sender->getName());
                        $moneyAmount = $this->data ["Cards"] [$playerName] ["Current_payments"];
                        $result = $this->api->reduceMoney($playerName, $moneyAmount);
                        switch ($result) {
                            case EconomyAPI::RET_INVALID :
                            case EconomyAPI::RET_NO_ACCOUNT :
                            case EconomyAPI::RET_CANCELLED :
                                $sender->sendMessage(Color::RED . $this->prefix . "금액 " . $moneyAmount . "의 납부가 실패했습니다.");
                                return false;
                            case EconomyAPI::RET_SUCCESS :
                                $this->data ["Cards"] [$playerName] = [
                                    "Current_payments" => 0,
                                    "Overdue" => false
                                ];
                                $sender->sendMessage(Color::RED . $this->prefix . "금액 " . $moneyAmount . "의 납부가 성공했습니다.");
                                return true;
                        }
                }
        }
        return false;
    }

    public function getHelp()
    {
        return [
            "/신용결제 <닉네임> <돈 양>",
            "/신용 결제금액"
        ];
    }
}

?>
