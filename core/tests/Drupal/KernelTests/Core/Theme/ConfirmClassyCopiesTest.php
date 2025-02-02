<?php

namespace Drupal\KernelTests\Core\Theme;

use Drupal\KernelTests\KernelTestBase;

/**
 * Confirms that theme assets copied from Classy have not been changed.
 *
 * If a copied Classy asset is changed, it should no longer be in a /classy
 * subdirectory. The files there should be exact copies from Classy. Once it has
 * changed, it is custom to the theme and should be moved to a different
 * location.
 *
 * @group Theme
 */
class ConfirmClassyCopiesTest extends KernelTestBase {

  /**
   * Tests Classy's assets have not been altered.
   */
  public function testClassyHashes() {
    $theme_path = $this->container->get('extension.list.theme')->getPath('classy');
    foreach (['images', 'css', 'js', 'templates'] as $type => $sub_folder) {
      $asset_path = "$theme_path/$sub_folder";
      $directory = new \RecursiveDirectoryIterator($asset_path, \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS);
      $iterator = new \RecursiveIteratorIterator($directory);
      $this->assertGreaterThan(0, iterator_count($iterator));
      foreach ($iterator as $fileinfo) {
        $filename = $fileinfo->getFilename();
        $this->assertSame(
          $this->getClassyHash($sub_folder, $filename),
          md5_file($fileinfo->getPathname()),
          "$filename has expected hash"
        );
      }
    }
  }

  /**
   * Confirms that files copied from Classy have not been altered.
   *
   * The /classy subdirectory in a theme's css, js and images directories is for
   * unaltered copies of files from Classy. If a file in that subdirectory has
   * changed, then it is custom to that theme and should be moved to a different
   * directory. Additional information can be found in the README.txt of each of
   * those /classy subdirectories.
   *
   * @param string $theme
   *   The theme being tested.
   * @param string $path_replace
   *   A string to replace paths found in CSS so relative URLs don't cause the
   *   hash to differ.
   * @param string[] $filenames
   *   Provides list of every asset copied from Classy.
   *
   * @dataProvider providerTestClassyCopies
   */
  public function testClassyCopies($theme, $path_replace, array $filenames) {
    $theme_path = $this->container->get('extension.list.theme')->getPath($theme);

    foreach (['images', 'css', 'js', 'templates'] as $sub_folder) {
      $asset_path = "$theme_path/$sub_folder/classy";
      // If a theme has completely customized all files of a type there is
      // potentially no Classy subdirectory for that type. Tests can be skipped
      // for that type.
      if (!file_exists($asset_path)) {
        $this->assertEmpty($filenames[$sub_folder]);
        continue;
      }

      // Create iterators to collect all files in a asset directory.
      $directory = new \RecursiveDirectoryIterator($asset_path, \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS);
      $iterator = new \RecursiveIteratorIterator($directory);
      $filecount = 0;
      foreach ($iterator as $fileinfo) {
        $filename = $fileinfo->getFilename();
        if ($filename === 'README.txt') {
          continue;
        }

        $filecount++;

        // Replace paths in the contents so the hash will match Classy's hashes.
        $contents = file_get_contents($fileinfo->getPathname());
        $contents = str_replace('(' . $path_replace, '(../../../../', $contents);
        $contents = str_replace('(../../../images/classy/icons', '(../../images/icons', $contents);
        preg_match_all("/attach_library\('.+\/classy\.(.+)'/", $contents, $classy_attach_library_matches);
        if (!empty($classy_attach_library_matches[0])) {
          $library_module = $classy_attach_library_matches[1][0];
          $contents = str_replace("'$theme/classy.$library_module'", "'classy/$library_module'", $contents);
        }

        $this->assertContains($filename, $filenames[$sub_folder], "$sub_folder file: $filename not present.");
        $this->assertSame(
          $this->getClassyHash($sub_folder, $filename),
          md5($contents),
          "$filename is in the theme's /classy subdirectory, but the file contents no longer match the original file from Classy. This should be moved to a new directory and libraries should be updated. The file can be removed from the data provider."
        );
      }
      $this->assertCount($filecount, $filenames[$sub_folder], "Different count for $sub_folder files in the /classy subdirectory. If a file was added to /classy, it shouldn't have been. If it was intentionally removed, it should also be removed from this test's data provider.");
    }
  }

  /**
   * Provides lists of filenames for a theme's asset files copied from Classy.
   *
   * @return array
   *   Theme name, how to replace a path to core assets and asset file names.
   */
  public function providerTestClassyCopies() {
    return [
      'umami' => [
        'theme-name' => 'umami',
        'path-replace' => '../../../../../../../',
        'filenames' => [
          'css' => [
            'action-links.css',
            'book-navigation.css',
            'breadcrumb.css',
            'button.css',
            'collapse-processed.css',
            'container-inline.css',
            'details.css',
            'dialog.css',
            'dropbutton.css',
            'exposed-filters.css',
            'field.css',
            'file.css',
            'form.css',
            'forum.css',
            'icons.css',
            'image-widget.css',
            'inline-form.css',
            'item-list.css',
            'link.css',
            'links.css',
            'media-embed-error.css',
            'media-library.css',
            'menu.css',
            'more-link.css',
            'node.css',
            'pager.css',
            'progress.css',
            'search-results.css',
            'tabledrag.css',
            'tableselect.css',
            'tablesort.css',
            'tabs.css',
            'textarea.css',
            'ui-dialog.css',
            'user.css',
          ],
          'js' => [
            'media_embed_ckeditor.theme.es6.js',
            'media_embed_ckeditor.theme.js',
          ],
          'images' => [
            'application-octet-stream.png',
            'application-pdf.png',
            'application-x-executable.png',
            'audio-x-generic.png',
            'forum-icons.png',
            'image-x-generic.png',
            'package-x-generic.png',
            'text-html.png',
            'text-plain.png',
            'text-x-generic.png',
            'text-x-script.png',
            'video-x-generic.png',
            'x-office-document.png',
            'x-office-presentation.png',
            'x-office-spreadsheet.png',
          ],
          'templates' => [
            'node-edit-form.html.twig',
            'image-widget.html.twig',
            'node-add-list.html.twig',
            'filter-guidelines.html.twig',
            'filter-tips.html.twig',
            'file-managed-file.html.twig',
            'text-format-wrapper.html.twig',
            'filter-caption.html.twig',
            'rdf-metadata.html.twig',
            'help-section.html.twig',
            'progress-bar.html.twig',
            'form-element-label.html.twig',
            'datetime-wrapper.html.twig',
            'fieldset.html.twig',
            'datetime-form.html.twig',
            'textarea.html.twig',
            'details.html.twig',
            'form-element.html.twig',
            'radios.html.twig',
            'item-list.html.twig',
            'aggregator-feed.html.twig',
            'item-list--search-results.html.twig',
            'table.html.twig',
            'forum-list.html.twig',
            'forum-icon.html.twig',
            'forums.html.twig',
            'maintenance-page.html.twig',
            'book-export-html.html.twig',
            'html.html.twig',
            'region.html.twig',
            'book-all-books-block.html.twig',
            'book-tree.html.twig',
            'book-navigation.html.twig',
            'toolbar.html.twig',
            'comment.html.twig',
            'taxonomy-term.html.twig',
            'media-embed-error.html.twig',
            'book-node-export-html.html.twig',
            'links--node.html.twig',
            'page-title.html.twig',
            'search-result.html.twig',
            'aggregator-item.html.twig',
            'media.html.twig',
            'mark.html.twig',
            'forum-submitted.html.twig',
            'username.html.twig',
            'user.html.twig',
            'time.html.twig',
            'image.html.twig',
            'field--text.html.twig',
            'field--text-long.html.twig',
            'file-audio.html.twig',
            'field--comment.html.twig',
            'link-formatter-link-separate.html.twig',
            'field.html.twig',
            'file-link.html.twig',
            'field--text-with-summary.html.twig',
            'field--node--uid.html.twig',
            'field--node--title.html.twig',
            'field--node--created.html.twig',
            'file-video.html.twig',
            'links--media-library-menu.html.twig',
            'media--media-library.html.twig',
            'views-view-unformatted--media-library.html.twig',
            'container--media-library-content.html.twig',
            'media-library-item--small.html.twig',
            'container--media-library-widget-selection.html.twig',
            'media-library-wrapper.html.twig',
            'media-library-item.html.twig',
            'views-mini-pager.html.twig',
            'views-exposed-form.html.twig',
            'views-view-grouping.html.twig',
            'views-view-summary.html.twig',
            'views-view-table.html.twig',
            'views-view-row-rss.html.twig',
            'views-view-summary-unformatted.html.twig',
            'views-view.html.twig',
            'block.html.twig',
            'block--local-actions-block.html.twig',
            'block--system-menu-block.html.twig',
            'block--local-tasks-block.html.twig',
          ],
        ],
      ],
      'claro' => [
        'theme-name' => 'claro',
        'path-replace' => '../../../../../',
        'filenames' => [
          'css' => [
            'book-navigation.css',
            'container-inline.css',
            'exposed-filters.css',
            'field.css',
            'file.css',
            'forum.css',
            'icons.css',
            'indented.css',
            'inline-form.css',
            'item-list.css',
            'link.css',
            'links.css',
            'media-embed-error.css',
            'menu.css',
            'more-link.css',
            'node.css',
            'search-results.css',
            'tablesort.css',
            'textarea.css',
            'ui-dialog.css',
          ],
          'js' => [
            'media_embed_ckeditor.theme.es6.js',
            'media_embed_ckeditor.theme.js',
          ],
          'images' => [
            'application-octet-stream.png',
            'application-pdf.png',
            'application-x-executable.png',
            'audio-x-generic.png',
            'forum-icons.png',
            'image-x-generic.png',
            'package-x-generic.png',
            'text-html.png',
            'text-plain.png',
            'text-x-generic.png',
            'text-x-script.png',
            'video-x-generic.png',
            'x-office-document.png',
            'x-office-presentation.png',
            'x-office-spreadsheet.png',
          ],
          'templates' => [
            'filter-caption.html.twig',
            'rdf-metadata.html.twig',
            'help-section.html.twig',
            'progress-bar.html.twig',
            'textarea.html.twig',
            'radios.html.twig',
            'item-list.html.twig',
            'aggregator-feed.html.twig',
            'item-list--search-results.html.twig',
            'table.html.twig',
            'forum-list.html.twig',
            'forum-icon.html.twig',
            'forums.html.twig',
            'book-export-html.html.twig',
            'html.html.twig',
            'region.html.twig',
            'menu.html.twig',
            'book-all-books-block.html.twig',
            'book-tree.html.twig',
            'book-navigation.html.twig',
            'toolbar.html.twig',
            'comment.html.twig',
            'node.html.twig',
            'taxonomy-term.html.twig',
            'media-embed-error.html.twig',
            'book-node-export-html.html.twig',
            'links--node.html.twig',
            'page-title.html.twig',
            'search-result.html.twig',
            'aggregator-item.html.twig',
            'media.html.twig',
            'mark.html.twig',
            'forum-submitted.html.twig',
            'username.html.twig',
            'user.html.twig',
            'time.html.twig',
            'image.html.twig',
            'field--text.html.twig',
            'field--text-long.html.twig',
            'file-audio.html.twig',
            'field--comment.html.twig',
            'link-formatter-link-separate.html.twig',
            'field.html.twig',
            'field--text-with-summary.html.twig',
            'field--node--uid.html.twig',
            'field--node--title.html.twig',
            'field--node--created.html.twig',
            'file-video.html.twig',
            'links--media-library-menu.html.twig',
            'media--media-library.html.twig',
            'container--media-library-content.html.twig',
            'media-library-item--small.html.twig',
            'container--media-library-widget-selection.html.twig',
            'media-library-wrapper.html.twig',
            'media-library-item.html.twig',
            'views-view-grouping.html.twig',
            'views-view-summary.html.twig',
            'views-view-table.html.twig',
            'views-view-row-rss.html.twig',
            'views-view-summary-unformatted.html.twig',
            'views-view.html.twig',
            'block--system-branding-block.html.twig',
            'block--search-form-block.html.twig',
            'block.html.twig',
            'block--system-menu-block.html.twig',
          ],
        ],
      ],
      'seven' => [
        'theme-name' => 'seven',
        'path-replace' => '../../../../../',
        'filenames' => [
          'css' => [
            'action-links.css',
            'book-navigation.css',
            'breadcrumb.css',
            'button.css',
            'collapse-processed.css',
            'container-inline.css',
            'dropbutton.css',
            'exposed-filters.css',
            'field.css',
            'file.css',
            'form.css',
            'forum.css',
            'icons.css',
            'image-widget.css',
            'indented.css',
            'inline-form.css',
            'item-list.css',
            'link.css',
            'links.css',
            'media-embed-error.css',
            'media-library.css',
            'menu.css',
            'messages.css',
            'more-link.css',
            'node.css',
            'pager.css',
            'progress.css',
            'search-results.css',
            'tabledrag.css',
            'tableselect.css',
            'tablesort.css',
            'tabs.css',
            'textarea.css',
            'ui-dialog.css',
            'user.css',
          ],
          'js' => [
            'media_embed_ckeditor.theme.es6.js',
            'media_embed_ckeditor.theme.js',
          ],
          'images' => [
            'application-octet-stream.png',
            'application-pdf.png',
            'application-x-executable.png',
            'audio-x-generic.png',
            'forum-icons.png',
            'image-x-generic.png',
            'package-x-generic.png',
            'text-html.png',
            'text-plain.png',
            'text-x-generic.png',
            'text-x-script.png',
            'video-x-generic.png',
            'x-office-document.png',
            'x-office-presentation.png',
            'x-office-spreadsheet.png',
          ],
          'templates' => [
            'filter-guidelines.html.twig',
            'filter-tips.html.twig',
            'file-managed-file.html.twig',
            'text-format-wrapper.html.twig',
            'filter-caption.html.twig',
            'rdf-metadata.html.twig',
            'help-section.html.twig',
            'progress-bar.html.twig',
            'status-messages.html.twig',
            'form-element-label.html.twig',
            'datetime-wrapper.html.twig',
            'fieldset.html.twig',
            'datetime-form.html.twig',
            'textarea.html.twig',
            'form-element.html.twig',
            'radios.html.twig',
            'item-list.html.twig',
            'aggregator-feed.html.twig',
            'item-list--search-results.html.twig',
            'table.html.twig',
            'forum-list.html.twig',
            'forum-icon.html.twig',
            'forums.html.twig',
            'book-export-html.html.twig',
            'html.html.twig',
            'region.html.twig',
            'menu.html.twig',
            'book-all-books-block.html.twig',
            'book-tree.html.twig',
            'menu-local-task.html.twig',
            'book-navigation.html.twig',
            'breadcrumb.html.twig',
            'toolbar.html.twig',
            'comment.html.twig',
            'node.html.twig',
            'taxonomy-term.html.twig',
            'media-embed-error.html.twig',
            'book-node-export-html.html.twig',
            'links--node.html.twig',
            'page-title.html.twig',
            'search-result.html.twig',
            'aggregator-item.html.twig',
            'media.html.twig',
            'mark.html.twig',
            'forum-submitted.html.twig',
            'username.html.twig',
            'user.html.twig',
            'time.html.twig',
            'image.html.twig',
            'field--text.html.twig',
            'field--text-long.html.twig',
            'file-audio.html.twig',
            'field--comment.html.twig',
            'link-formatter-link-separate.html.twig',
            'field.html.twig',
            'file-link.html.twig',
            'field--text-with-summary.html.twig',
            'field--node--uid.html.twig',
            'field--node--title.html.twig',
            'field--node--created.html.twig',
            'file-video.html.twig',
            'links--media-library-menu.html.twig',
            'media--media-library.html.twig',
            'container--media-library-content.html.twig',
            'media-library-item--small.html.twig',
            'container--media-library-widget-selection.html.twig',
            'media-library-wrapper.html.twig',
            'media-library-item.html.twig',
            'views-mini-pager.html.twig',
            'views-exposed-form.html.twig',
            'views-view-grouping.html.twig',
            'views-view-summary.html.twig',
            'views-view-table.html.twig',
            'views-view-row-rss.html.twig',
            'views-view-summary-unformatted.html.twig',
            'views-view.html.twig',
            'block--system-branding-block.html.twig',
            'block--search-form-block.html.twig',
            'block.html.twig',
            'block--system-menu-block.html.twig',
            'block--local-tasks-block.html.twig',
          ],
        ],
      ],
      // Will be populated when Classy libraries are copied to Bartik.
      'bartik' => [
        'theme-name' => 'bartik',
        'path-replace' => '../../../../../',
        'filenames' => [
          'css' => [
            'media-library.css',
            'action-links.css',
            'file.css',
            'dropbutton.css',
            'book-navigation.css',
            'tableselect.css',
            'ui-dialog.css',
            'user.css',
            'item-list.css',
            'image-widget.css',
            'field.css',
            'tablesort.css',
            'tabs.css',
            'forum.css',
            'progress.css',
            'collapse-processed.css',
            'details.css',
            'inline-form.css',
            'link.css',
            'textarea.css',
            'links.css',
            'form.css',
            'exposed-filters.css',
            'tabledrag.css',
            'indented.css',
            'messages.css',
            'pager.css',
            'search-results.css',
            'button.css',
            'node.css',
            'dialog.css',
            'menu.css',
            'icons.css',
            'breadcrumb.css',
            'media-embed-error.css',
            'container-inline.css',
            'more-link.css',
          ],
          'js' => [
            'media_embed_ckeditor.theme.es6.js',
            'media_embed_ckeditor.theme.js',
          ],
          'images' => [
            'application-octet-stream.png',
            'application-pdf.png',
            'application-x-executable.png',
            'audio-x-generic.png',
            'forum-icons.png',
            'image-x-generic.png',
            'package-x-generic.png',
            'text-html.png',
            'text-plain.png',
            'text-x-generic.png',
            'text-x-script.png',
            'video-x-generic.png',
            'x-office-document.png',
            'x-office-presentation.png',
            'x-office-spreadsheet.png',
          ],
          'templates' => [
            'node-edit-form.html.twig',
            'image-widget.html.twig',
            'node-add-list.html.twig',
            'filter-guidelines.html.twig',
            'filter-tips.html.twig',
            'file-managed-file.html.twig',
            'text-format-wrapper.html.twig',
            'filter-caption.html.twig',
            'rdf-metadata.html.twig',
            'help-section.html.twig',
            'progress-bar.html.twig',
            'form-element-label.html.twig',
            'datetime-wrapper.html.twig',
            'fieldset.html.twig',
            'datetime-form.html.twig',
            'textarea.html.twig',
            'details.html.twig',
            'form-element.html.twig',
            'radios.html.twig',
            'item-list.html.twig',
            'aggregator-feed.html.twig',
            'item-list--search-results.html.twig',
            'table.html.twig',
            'forum-list.html.twig',
            'forum-icon.html.twig',
            'forums.html.twig',
            'book-export-html.html.twig',
            'html.html.twig',
            'region.html.twig',
            'menu.html.twig',
            'book-all-books-block.html.twig',
            'book-tree.html.twig',
            'menu-local-task.html.twig',
            'book-navigation.html.twig',
            'breadcrumb.html.twig',
            'toolbar.html.twig',
            'menu-local-tasks.html.twig',
            'taxonomy-term.html.twig',
            'media-embed-error.html.twig',
            'book-node-export-html.html.twig',
            'links--node.html.twig',
            'search-result.html.twig',
            'aggregator-item.html.twig',
            'media.html.twig',
            'mark.html.twig',
            'forum-submitted.html.twig',
            'username.html.twig',
            'user.html.twig',
            'time.html.twig',
            'image.html.twig',
            'field--text.html.twig',
            'field--text-long.html.twig',
            'file-audio.html.twig',
            'field--comment.html.twig',
            'link-formatter-link-separate.html.twig',
            'field.html.twig',
            'file-link.html.twig',
            'field--text-with-summary.html.twig',
            'field--node--uid.html.twig',
            'field--node--title.html.twig',
            'field--node--created.html.twig',
            'file-video.html.twig',
            'links--media-library-menu.html.twig',
            'media--media-library.html.twig',
            'views-view-unformatted--media-library.html.twig',
            'container--media-library-content.html.twig',
            'media-library-item--small.html.twig',
            'container--media-library-widget-selection.html.twig',
            'media-library-wrapper.html.twig',
            'media-library-item.html.twig',
            'views-mini-pager.html.twig',
            'views-exposed-form.html.twig',
            'views-view-grouping.html.twig',
            'views-view-summary.html.twig',
            'views-view-table.html.twig',
            'views-view-row-rss.html.twig',
            'views-view-summary-unformatted.html.twig',
            'views-view.html.twig',
            'block--local-actions-block.html.twig',
            'block--local-tasks-block.html.twig',
          ],
        ],
      ],
    ];
  }

  /**
   * Gets the hash of a Classy asset.
   *
   * @param string $type
   *   The asset type.
   * @param string $file
   *   The asset filename.
   *
   * @return string
   *   A hash for the file.
   */
  protected function getClassyHash($type, $file) {
    static $hashes = [
      'css' => [
        'action-links.css' => '6abb88c2b3b6884c1a64fa5ca4853d45',
        'book-navigation.css' => 'e8219368d360bd4a10763610ada85a1c',
        'breadcrumb.css' => '14268f8071dffd40ce7a39862b8fbc56',
        'button.css' => '3abebf58e144fd4150d80facdbe5d10f',
        'collapse-processed.css' => 'e928df55485662a4499c9ba12def22e6',
        'container-inline.css' => 'ae9caee6071b319ac97bf0bb3e14b542',
        'details.css' => 'fdd0606ea856072f5e6a19ab1a2e850e',
        'dialog.css' => 'f30e4423380f5f01d02ef0a93e010c53',
        'dropbutton.css' => 'f8e4b0b81ff60206b27f622e85a6a0ee',
        'exposed-filters.css' => '396a5f76dafec5f78f4e736f69a0874f',
        'field.css' => '8f4718bc926eea7e007ecfd6f410ee8d',
        'file.css' => '7f36f62ca67c57a82f9d9e882918a01b',
        'form.css' => 'a8733b00eebffbc3293779cb779c808e',
        'forum.css' => '8aad2d86dfd29818e991757581cd7ab8',
        'icons.css' => '56f623bd343b9bc7e7ac3e3e95d7f3ce',
        'image-widget.css' => '2da54829199f64a2c390930c3b0913a3',
        'indented.css' => '48e214a106d9fede1e05aa10b4796361',
        'inline-form.css' => 'cc5cbfd34511d9021a53ec693c110740',
        'item-list.css' => '1d519afe6007f4b01e00f22b0ba8bf33',
        'link.css' => '22f42d430fe458080a7739c70a2d2ea5',
        'links.css' => '21fe64349f5702cd5b89104a1d3b9cd3',
        'media-embed-error.css' => 'ab7f4c91f7b312122d30d7e09bb1bcc4',
        'media-library.css' => 'bb405519d30970c721405452dfb7b38e',
        'menu.css' => 'c4608b4ac9aafce1f6e0d21c6e6e6ee8',
        'messages.css' => '2930ea9bebf4d1658e9bdc3b1f83bd43',
        'more-link.css' => 'b2ebfb826e035334340193b42246b180',
        'node.css' => '81ea0a3fef211dbc32549ac7f39ec646',
        'pager.css' => 'd10589366720f9c15b66df434baab4da',
        'progress.css' => '5147a9b07ede9f456c6a3f3efeb520e1',
        'search-results.css' => 'ce3ca8fcd54e72f142ba29da5a3a5c9a',
        'tabledrag.css' => '98d24ff864c7699dfa6da9190c5e70df',
        'tableselect.css' => '8e966ac85a0cc60f470717410640c8fe',
        'tablesort.css' => 'f6ed3b44832bebffa09fc3b4b6ce27ab',
        'tabs.css' => 'e58827db5c767c41b67488244c14056c',
        'textarea.css' => '2bc390c137c5205bbcd7645d6c1c86de',
        'ui-dialog.css' => '4a3d036007ba8c8c80f4a21a369c72cc',
        'user.css' => '0ec6acc22567a7c9c228f04b5a97c711',
      ],
      'js' => [
        'media_embed_ckeditor.theme.es6.js' => 'decf95c314bf22c642fb630179502e43',
        'media_embed_ckeditor.theme.js' => 'f8e192b79f25d2b61a6ff43b9733ec72',
      ],
      'images' => [
        'application-octet-stream.png' => 'fef73511632890590b5ae0a13c99e4bf',
        'application-pdf.png' => 'bb41f8b679b9d93323b30c87fde14de9',
        'application-x-executable.png' => 'fef73511632890590b5ae0a13c99e4bf',
        'audio-x-generic.png' => 'f7d0e6fbcde58594bd1102db95e3ea7b',
        'forum-icons.png' => 'dfa091b192819cc14523ccd653e7b5ff',
        'image-x-generic.png' => '9aca2e02c3cdbb391ca721d40fa4c0c6',
        'package-x-generic.png' => 'bb8581301a2030b48ff3c67374eed88a',
        'text-html.png' => '9d2d3003a786ab392d42744b2d064eec',
        'text-plain.png' => '1b769df473f54d6f78f7aba79ec25e12',
        'text-x-generic.png' => '1b769df473f54d6f78f7aba79ec25e12',
        'text-x-script.png' => 'f9dc156d35298536011ea48226b21682',
        'video-x-generic.png' => 'a5dc89b884a8a1b666c15bb41fd88ee9',
        'x-office-document.png' => '48e0c92b5dec1a027f43a5c6fe190f39',
        'x-office-presentation.png' => '8ba9f51c97a2b47de2c8c117aafd7dcd',
        'x-office-spreadsheet.png' => 'fc5d4b32f259ea6d0f960b17a0886f63',
      ],
      'templates' => [
        'node-edit-form.html.twig' => '62333c862703b199fe339677ce6783ac',
        'file-widget-multiple.html.twig' => '93425e782dabe54b88b1516dc681f9ce',
        'image-widget.html.twig' => '03d1151c7e99999174a0113d21375372',
        'file-upload-help.html.twig' => 'd8fcf1f79c4eff6c89539c17d03c4731',
        'node-add-list.html.twig' => '43cef03ea415399b8e51e2e363479702',
        'filter-guidelines.html.twig' => '250f9abf18cfc45f174d994dc505585b',
        'filter-tips.html.twig' => 'fefcab317b602cbfe7f608bc481be889',
        'file-managed-file.html.twig' => 'ee735232c3d782f09178bc56df8f89b1',
        'text-format-wrapper.html.twig' => '9b9f43cee239648f0c6966c68fc4d72e',
        'filter-caption.html.twig' => '7cc9ce9634332604bd3a565af2ef0cd5',
        'rdf-metadata.html.twig' => 'ebf2c20050b6a89b04168ce66d0a55dc',
        'help-section.html.twig' => '4f98fbb266cf9069a4604049c848a4c2',
        'progress-bar.html.twig' => 'ad07ee846d10bb46eb71d4c81d5bce75',
        'status-messages.html.twig' => '5c8403daec6d92b35407a893c04a6a36',
        'container.html.twig' => 'd88ec99c466a11fa9b83e3594675cc9a',
        'input.html.twig' => 'b844ef5f74df3058bd6ff9ec024d907b',
        'form-element-label.html.twig' => '21026361fa9ba15ccdc823d6bb00a6d9',
        'datetime-wrapper.html.twig' => '765aa519f7e4ae36ee4726ae2633de6d',
        'fieldset.html.twig' => 'c22ed7e50177391f1fc4b1fbacc8a211',
        'form.html.twig' => '0767ff441543553d51443b970c4b736b',
        'datetime-form.html.twig' => '649c36a2900c556b8a1385c1fa755281',
        'checkboxes.html.twig' => 'a5faa5fdd7de4aa42045753db65ffb0b',
        'textarea.html.twig' => '4a583e6afa9c04ed6a7b2a36ba172f16',
        'field-multiple-value-form.html.twig' => '152e4e8b944d093742f5a1c3ce65b28b',
        'dropbutton-wrapper.html.twig' => 'ed30bb07a6a5ac007f985b935d8a07a9',
        'details.html.twig' => 'ac7d9de69428a0a04933775d1cfd78c6',
        'select.html.twig' => '4dd8e366a76518187462930e1fe487ae',
        'form-element.html.twig' => '0dbef99d7eb04f81f38af1dd5c0954a0',
        'confirm-form.html.twig' => '12f6086c3742ef59e1b25b0cadc1f890',
        'radios.html.twig' => 'd938c3dd61fc99b79cf4f07ad673ee83',
        'item-list.html.twig' => '869d9d6a7c79844b69e88a622eb1e4b1',
        'aggregator-feed.html.twig' => 'b9c4dc3b384bca32041edf63c598e197',
        'item-list--search-results.html.twig' => 'fe10f094b0caff314d63842bd332cfb3',
        'table.html.twig' => '8a3c6dc67452ec6372d56cfb496c711d',
        'forum-list.html.twig' => '1e1d16f6359350f08c531260d3919149',
        'forum-icon.html.twig' => 'ad4bb2036adf04888e0bf82dfd93890e',
        'forums.html.twig' => '0a46228fa1f90089dfc67e7fe4c6690b',
        'page.html.twig' => '14210892625e61cb8570cda6a3a932ce',
        'maintenance-page.html.twig' => '627dd51c95d2930844c584a385eff3c5',
        'book-export-html.html.twig' => 'd09aadf9e8e05d50145872f86544d0d0',
        'html.html.twig' => '7bc0366efb52d727aa6fb48d4d3d75fb',
        'region.html.twig' => 'b1c621b93e5ab7334151fe31d4c163a8',
        'menu.html.twig' => 'a85f0d836f62a4a2834934b15b5cc4f3',
        'book-all-books-block.html.twig' => '7210413d32aafa0c5413f1c29b9a87cc',
        'book-tree.html.twig' => '027a2d9aa9d8e13afeb87b2f830f4339',
        'links.html.twig' => '158ded06d80f3071b051a3263d9bdeac',
        'vertical-tabs.html.twig' => 'cccdb9b454f5befdfedfc629a6a51e4d',
        'pager.html.twig' => '3ad3ad68fa70d7f6b69676d1e28b0338',
        'menu-local-task.html.twig' => 'eb20b3b773365137893f25e31a9768e3',
        'book-navigation.html.twig' => '6ecc18ab554936f9e3b4cadd92a94d66',
        'breadcrumb.html.twig' => '3de826e26e00fbe1a661b1f64d4c45d0',
        'menu-local-action.html.twig' => 'ff1dfb632b6235a304146aaeaa49c3ca',
        'toolbar.html.twig' => '151657ffe8fc7c8f0d9571a8b0684294',
        'menu-local-tasks.html.twig' => 'ab94198d8dba71464c647aca349dcfd3',
        'comment.html.twig' => '68718b6de9a0d21f5180a9fbcc40987f',
        'node.html.twig' => '48526d497ead869ec3a78d83787c0311',
        'taxonomy-term.html.twig' => '63e39620cd877c85297914fef61930de',
        'media-embed-error.html.twig' => '83621141a91e525cd4df15c1d93b58b2',
        'book-node-export-html.html.twig' => 'e3f896d5f4f69c28256807fb57382eb5',
        'links--node.html.twig' => '746362f23d45654540368b963e6b9feb',
        'page-title.html.twig' => '73e9f3f4933b1a1b789db6e4a6556355',
        'search-result.html.twig' => '5676e5f62d82fcb1a2588da2197d1455',
        'aggregator-item.html.twig' => '5d1d474391f1f1cce4731b5b33e04df4',
        'media.html.twig' => 'ba8a5c1035a7d9f958d6bb16ea862a74',
        'mark.html.twig' => '8a162708ce2ca9c3fc272daf4a92897e',
        'forum-submitted.html.twig' => '9566e863a16db23a76ec3e289e16fa47',
        'username.html.twig' => '92ebba7253772a4389c76c27f7d2b0f0',
        'user.html.twig' => 'b265def674626cf35ac46f1bbad3e28f',
        'time.html.twig' => '74a5bbb48d3bce23ccab80993e1d04c1',
        'image.html.twig' => 'b8dd5d5b7e1bf594f85411f28eb29440',
        'field--text.html.twig' => '4cb76e10e41ccf0b7cf350be600853c5',
        'field--text-long.html.twig' => '898425e3da212ed81ff01c7b624033fd',
        'file-audio.html.twig' => '2dec3e01acc93858bdad64ccac1d0fc1',
        'field--comment.html.twig' => 'c116dc85a39418301a676b9138a8d697',
        'link-formatter-link-separate.html.twig' => '944fe3b95472c411013da977d446f843',
        'field.html.twig' => '4da82841bf83d728ed2f3757de7402fb',
        'file-link.html.twig' => '0f10f3e79ecb9b6e82ef30c66e4ebcc6',
        'field--text-with-summary.html.twig' => '898425e3da212ed81ff01c7b624033fd',
        'field--node--uid.html.twig' => 'eec25d4a07d3447ba012a1f6bf8c2cea',
        'field--node--title.html.twig' => 'bd618d16c576614d2f2c6d3e60315559',
        'image-style.html.twig' => '6d2588a4453ed014601f66cd45baf00f',
        'field--node--created.html.twig' => '7a33b0533f7bd12cc81af52f4151cb7c',
        'image-formatter.html.twig' => '9bc966dd2fe1a913cd7a89fbd243d947',
        'file-video.html.twig' => '9f58b817bf059a86300d2757c76f0d97',
        'links--media-library-menu.html.twig' => '31f4f0f507af5dde490e9a992fa89db6',
        'media--media-library.html.twig' => '72a2af7f2f0ac3013f0a20f887e4d48a',
        'views-view-unformatted--media-library.html.twig' => '4071005d8fac358614742983d77940a2',
        'container--media-library-content.html.twig' => 'dede085892e5039aefb3c2cc2dc2074b',
        'media-library-item--small.html.twig' => 'e45cfe56961b3419feb8008a5abb7357',
        'container--media-library-widget-selection.html.twig' => '70759ddf4d23f5cdd7ef62f3d3e0eda0',
        'media-library-wrapper.html.twig' => '3ca8cd32767c043cd376f8de42b70611',
        'media-library-item.html.twig' => '278c18d4f6ec1651a408a1fce3ec70a5',
        'views-mini-pager.html.twig' => '92f8935c78df8c61ba1bb03c34e305a8',
        'views-view-row-opml.html.twig' => 'defbd7edeff60ebb16a06414ef13db06',
        'views-exposed-form.html.twig' => 'd88119f917c62e0caa75ca0becc8c327',
        'views-view-grouping.html.twig' => 'e766e383b51511b86fc0815c94167c18',
        'views-view-summary.html.twig' => '38639cb9e815e387782b126cb613bb40',
        'views-view-table.html.twig' => 'bff52235899b901aa6cd225e7e71bf31',
        'views-view-list.html.twig' => '7480144ffa90384ad2c3162f03ad042f',
        'views-view-unformatted.html.twig' => 'b2faf1bd77678dba68e1e6bb05c3a219',
        'views-view-row-rss.html.twig' => '0721785e0471ca23bbed6358dde0df68',
        'views-view-mapping-test.html.twig' => '818431786e1d19df33cecccad98d5a22',
        'views-view-opml.html.twig' => '4ab17668908dcd4af75d35f891f97fff',
        'views-view-summary-unformatted.html.twig' => '76f6e5882aa7fe6bc0440b66e85a0a6c',
        'views-view.html.twig' => 'd20ba03bc36703828bb7651baa15f28f',
        'views-view-grid.html.twig' => '8f4ea66bf949530d31a79a44f3d87650',
        'views-view-rss.html.twig' => 'f4e49d0d8df01019245c51ff2a4259c2',
        'block--system-branding-block.html.twig' => '73f89a493071e67c6ed3d17fff8e3a95',
        'block--search-form-block.html.twig' => '7fef4c274e4487ba887fdeaa41acb5ca',
        'block.html.twig' => '9b68163e596c63921119ff8f20c6f157',
        'block--local-actions-block.html.twig' => '6afe8adb14d3f37ec374400fecd5b809',
        'block--system-menu-block.html.twig' => '242f41ff8a0f71bbccece61bf8e29e2f',
        'block--local-tasks-block.html.twig' => 'd462897ef5c9b6935ce801de122bce30',
      ],
    ];
    $this->assertArrayHasKey($type, $hashes);
    $this->assertArrayHasKey($file, $hashes[$type]);
    return $hashes[$type][$file];
  }

}
