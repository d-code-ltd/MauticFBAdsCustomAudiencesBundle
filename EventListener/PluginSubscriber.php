<?php

/*
 * @copyright   2017 Trinoco. All rights reserved
 * @author      Trinoco
 *
 * @link        http://trinoco.nl
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */


namespace MauticPlugin\MauticFBAdsCustomAudiencesBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\PluginBundle\Event\PluginIntegrationEvent;
use Mautic\PluginBundle\PluginEvents;

use MauticPlugin\MauticFBAdsCustomAudiencesBundle\Helper\FbAdsApiHelper;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Psr\Log\LoggerInterface;

/**
 * Class PluginSubscriber.
 */
class PluginSubscriber extends CommonSubscriber
{
  /**
   * CampaignSubscriber constructor.
   *
   * @param IntegrationHelper       $integrationHelper
   * @param LoggerInterface          $logger
   */
  public function __construct(
      IntegrationHelper $integrationHelper,
      LoggerInterface $logger        
  ) {
      $this->integrationHelper = $integrationHelper;
      $this->logger = $logger;        
  }  

  /**
   * @return array
   */
  public static function getSubscribedEvents()
  {
    return [
      PluginEvents::PLUGIN_ON_INTEGRATION_CONFIG_SAVE => ['onIntegrationConfigSave', 0],
    ];
  }

  public function onIntegrationConfigSave(PluginIntegrationEvent $event) {
    if ($event->getIntegrationName() == 'FBAdsCustomAudiences') {
      //$integration = $event->getIntegration();
      $changes = $event->getEntity()->getChanges();

      if (isset($changes['isPublished'])) {
        try {
          $integration = $event->getIntegration();
          $api = FbAdsApiHelper::init($integration);

          if ($api) {
            $lists = $this->em->getRepository('MauticLeadBundle:LeadList')->getLists();

            if ($changes['isPublished'][1] == 0) {
              foreach ($lists as $list) {
                FbAdsApiHelper::deleteList($list['name']);
              }
            }
            else {
              $listsLeads =  $this->em->getRepository('MauticLeadBundle:LeadList')->getLeadsByList($lists);
              foreach ($lists as $list) {
                $listEntity = $this->em->getRepository('MauticLeadBundle:LeadList')->getEntity($list['id']);
                $audience = FbAdsApiHelper::addList($listEntity);

                $leads = $listsLeads[$listEntity->getId()];
                $users = array();

                foreach ($leads as $lead) {
                  if (!empty($lead['email'])){
                    $users[] = $lead['email'];
                  }
                }
              
                if (!empty($users)){
                  FbAdsApiHelper::addUsers($audience, $users);
                }
              }
            }
          }
        } catch (\Exception $e){
          $entity = $event->getEntity();
          $entity->setIsPublished(false);
          $event->setEntity($entity);


          $this->logger->warning($event->getIntegrationName().": Facebook authorization failed: ". $e->getMessage()); 
        }
      }
    }
  }
}