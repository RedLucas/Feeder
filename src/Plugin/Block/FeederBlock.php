<?php

namespace Drupal\feeder\Plugin\Block;

use Drupal\aggregator\Entity\Feed;
use Drupal\Core\Form\FormStateInterface;
use Drupal\aggregator\FeedStorageInterface;
use Drupal\aggregator\ItemStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aggregator\Plugin\Block\AggregatorFeedBlock;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an advanced 'Aggregator feed' block with the latest items from the feed.
 *
 * @Block(
 *   id = "feeder_block",
 *   admin_label = @Translation("Advanced aggregator feed"),
 *   category = @Translation("Lists (Views)")
 * )
 */
class FeederBlock extends AggregatorFeedBlock {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  protected $entityDisplayRepository;

  /**
   * Constructs an FeederBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\aggregator\FeedStorageInterface $feed_storage
   *   The entity storage for feeds.
   * @param \Drupal\aggregator\ItemStorageInterface $item_storage
   *   The entity storage for feed items.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FeedStorageInterface $feed_storage, ItemStorageInterface $item_storage, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $feed_storage, $item_storage);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayManager = $entity_display_repository;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('aggregator_feed'),
      $container->get('entity_type.manager')->getStorage('aggregator_item'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();
    $configuration['display_style'] = 'default';
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $form['display_style'] = [
      '#type' => 'select',
      '#title' => $this->t('')
      '#options' => $this->entityDisplayRepository->getViewModeOptions('aggregator_feed'),
      '#default_value' => $this->configuration['display_style'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    // Set the display style of the aggregator item and feed.
    $this->configuration['display_style'] = $form_state->getValue('display_style');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if ($feed = $this->feedStorage->load($this->configuration['feed'])) {
      // Get the item IDs for the items within the feed.
      $result = $this->getItemIds($feed);
      $build = [
        '#theme' => 'aggregator_feed',
        '#view_mode' => $this->configuration['display_style'],
        '#aggregator_feed' => $feed,
      ];
      if ($result) {
        $items = $this->getItemsByIds($result);

        $build['items'] = $this->entityTypeManager
          ->getViewBuilder('aggregator_item')
          ->viewMultiple($items, $this->configuration['display_style'], $feed->language()->getId());
        return $build;
      }
    }
  }

  /**
   * Get a list of item IDs from the feed, sorted and restricted by the block count.
   *
   * @param \Drupal\aggregator\Entity\Feed $feed
   *   The feed entity.
   */
  protected function getItemIds(Feed $feed) {
    return $this->itemStorage->getQuery()
        ->condition('fid', $feed->id())
        ->range(0, $this->configuration['block_count'])
        ->sort('timestamp', 'DESC')
        ->sort('iid', 'DESC')
        ->execute();
  }

  /**
   * Get the item entities from an array of item IDs.
   *
   * @param array $item_ids
   *   An array of item IDs.
   */
  public function getItemsByIds(array $item_ids) {
    return $this->itemStorage->loadMultiple($item_ids);
  }

}
