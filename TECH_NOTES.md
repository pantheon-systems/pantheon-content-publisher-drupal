## Documents

1. The most important part is `PantheonDocumentCollection::postSave()`.
This creates Drupal fields for Pantheon metadata fields and sets up
Search API integration.
2. Search API is essential because it provides views integration.
3. `PantheonContentPublisherController::webhook()` is called on publishing and
unpublishing documents. This clears caches, updates fields, updates the
Search API index and populates the `pantheon_document_images` queue with
images from the `content` field. If the webhook execution fails for any reason
then the collection will be stale and the document will need to be published
again to get back in sync.
4. The `pantheon_document_images`  queue worker downloads the images, creates
file and media entities, these are currently not used because it takes an
indeterminate  amount of time to download and process all images -- that's
why it's a queue. Perhaps `PantheonTagsToRenderable` could do a lookup for
every image and replace the image with a rendered media entity if the
lookup succeeds. While this would allow all the power of the media module,
it would also be very inconsistent.
5.`PantheonTagsToRenderable` is the server side equivalent of `preview.js`.
The latter comes from the Wordpress plugin. It processes the JSON array in
the `content` field of Pantheon documents. It's used preprocess this field
for Search API and also in the field formatter.

## Smart components

1. Smart components are simple fieldable entities. The fields are presented
in the appropriate JSON based format by
`PantheonSmartComponentController::listComponents`. This is field based, if
a component has no fields, it won't show up.
2. Since the data for each component instance is stored in Firebase on the
Pantheon side, the `pantheon_smart_instance` content entity uses a null
storage. Entities are created fresh when rendering is requested.
3. Rendering happens at two places: in `PantheonTagsToRenderable` and in
`PantheonSmartComponentController::viewSmartComponent`. For both the
technical challenge is ensuring proper caching metadata.
