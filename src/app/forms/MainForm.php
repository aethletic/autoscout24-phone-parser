<?php
namespace app\forms;

use php\jsoup\Element;
use std, gui, framework, app;
use php\jsoup\Jsoup;


class MainForm extends AbstractForm
{

    public $phonesData;
    public $clearPhones;
    public $rawPhones;
    
    public $usedDetailLinks = [];
    
    public $thread;
    
    /**
     * @event button.click-Left 
     */
    function doButtonClickLeft(UXMouseEvent $e = null)
    {    
        $currentPage = $this->pageStart->text;
        $maxPage = $this->pageEnd->text;
        
        $delay = (int) $this->delay->text;
        
        $this->phonesData = [];
        $this->clearPhones = [];
        $this->rawPhones = [];
        
        if ($this->resetUsedLinks->selected) {
           $this->usedDetailLinks = []; 
        }
        
        $this->phones->clear();
        $this->log->clear();
         
         $this->button->enabled = false;
        $this->thread = new Thread(function () use ($currentPage, $maxPage, $delay) {
            for ($currentPage = $this->pageStart->text; $currentPage <= $maxPage; $currentPage++) {
            
                $this->logger("Получение детальных ссылок со страницы {$currentPage} из {$maxPage}...");
                
                $links = self::parseDetailLinks(trim($this->link->text), $currentPage);
               
                if (!$links) {
                    $this->logger("Достигнут лимит последней страницы: " . ($currentPage - 1));
                    break;
                }         
                       
                $this->logger("Получено ссылок: " . count($links));
        
                foreach ($links as $link) {
                
                    if (in_array($link, $this->usedDetailLinks)) {
                        $this->logger("Проскаем использованную ссылку: {$link}");
                        continue;
                    }
                    
                    $this->usedDetailLinks[] = $link;
                    
                    $this->logger("Парсинг детальной страницы: {$link}");
                    $phone = self::parsePhoneFromDetailPage($link);
                    $this->logger("Найден номер: {$phone['phone']}");
                    $this->phonesData[] = $phone;
                    $this->clearPhones[] = $phone['phone'];
                    $this->rawPhones[] = $phone['text'];
                    $this->logger("Всего номеров получено: " . count($this->phonesData));
                    
                    uiLater(function() use ($phone) { 
                        $this->phones->text = "{$phone['phone']} | {$phone['text']}\n" . $this->phones->text;  
                        $this->uiPhones->text = "Номеров: " . count($this->phonesData);
                    });
                    
                    if ($delay > 0) {
                        $this->logger("Ждем {$delay} сек.");
                        sleep($delay);
                    }
                    
                } 
            }
            
            uiLater(function() { 
                $this->button->enabled = true;
            });
            
            $this->clearPhones = array_filter($this->clearPhones);
            $this->rawPhones = array_filter($this->rawPhones);
        });
            
        $this->thread->start();
        
    }

    /**
     * @event button3.click-Left 
     */
    function doButton3ClickLeft(UXMouseEvent $e = null)
    {    
        $countBefore = count($this->clearPhones);
        
        $this->clearPhones = array_unique($this->clearPhones);
        $this->rawPhones = array_unique($this->rawPhones);
        
        $countAfter = count($this->clearPhones);
        
        $this->toast("Удалено дубликатов: " . ($countBefore - $countAfter));
    }

    /**
     * @event show 
     */
    function doShow(UXWindowEvent $e = null)
    {    
        $this->combobox->selectedIndex = 0;
    }

    /**
     * @event buttonAlt.click-Left 
     */
    function doButtonAltClickLeft(UXMouseEvent $e = null)
    {    
        $this->fileChooser->initialFileName = "autoscout24-номера-" . count($this->clearPhones) . "шт";
        if($this->fileChooser->execute()) {
            if ($this->combobox->selectedIndex == 0) {
                $phones = $this->clearPhones;
            }
            
            if ($this->combobox->selectedIndex == 1) {
                $phones = $this->rawPhones;
            }
            
            $path = $this->fileChooser->file->getPath();
            
            if (file_put_contents($path, implode("\n", $phones))) {
                $this->toast("Номера успешно сохранены в: {$path}");
            } else {
                $this->toast("Ошибка при сохранении, возможно нет прав на запись.");
            }
        }
    }

    public static function parseDetailLinks($url, $page)
    {
        if (stripos($url, '?') !== false) {
            $parsedUrl = explode('?', $url);
            $query = parse_str(urldecode($parsedUrl[1]));
            $query['page'] = $page; 
            $url = "{$parsedUrl[0]}?" . http_build_query($query);
        } else {
             $query = [];
             $query['page'] = $page; 
             $url = "{$url}?" . http_build_query($query);
        }
        
        $html = file_get_contents($url);
     
        $doc = Jsoup::parseText($html);

        if ($doc->select(".cl-page-content")->text() == "Page number or size exceeded") {
            return false;
        }

        $block = $doc->select(".cldt-summary-titles");
        
        $detailLinks = [];
        
        foreach ($block as $element) {
             foreach ($element->select("a") as $link) {
                 $detailLinks[] = "https://www.autoscout24.ru" . $link->attr("href");
             }
        }
        
        unset($doc);
        unset($block);
        unset($element);
        
        return $detailLinks;
    }
    
    public static function parsePhoneFromDetailPage($url)
    {
        $html = file_get_contents($url);
        $doc = Jsoup::parseText($html);
        
        $block = $doc->select(".cldt-stage-vendor-buttons");

        foreach ($block as $element) { 
             foreach ($element->select("a") as $link) {
                 $href = $link->attr("href");
                 if (strpos($href, "tel:") === false) {
                     continue;
                 }
                 
                 $explodedHref = explode(':', $href);
                 return [
                     'phone' => end($explodedHref),
                     'href' => $link->attr("href"),
                     'text' => $link->text(),
                 ];
             }
        }
    }
    
    public function logger($text, $type = 'info') 
    {
        uiLater(function() use ($text, $type) {
            $this->log->text = "[{$type}] {$text}\n" . $this->log->text;
        });
    }
}
