<?php
namespace Sergeym\PreziMediaBundle\Provider;

use Sonata\MediaBundle\Model\MediaInterface;
use Sonata\MediaBundle\Provider\BaseVideoProvider;
use Sonata\CoreBundle\Model\Metadata;
use Symfony\Component\HttpFoundation\RedirectResponse;


class PreziProvider extends BaseVideoProvider
{
    /**
     * {@inheritdoc}
     */
    public function getHelperProperties(MediaInterface $media, $format, $options = [])
    {
        $box = $this->getBoxHelperProperties($media, $format, $options);

        $params = [
            //'src' => http_build_query($player_parameters),
            'frameborder' => isset($options['frameborder']) ? $options['frameborder'] : 0,
            'width' => $box->getWidth(),
            'height' => $box->getHeight(),
            'class' => isset($options['class']) ? $options['class'] : '',
            'allow_fullscreen' => isset($options['allowfullscreen']) ? true : false,
        ];

        return $params;
    }

    /**
     * {@inheritdoc}
     */
    public function getProviderMetadata()
    {
        return new Metadata($this->getName(), $this->getName().'.description', false, 'SonataMediaBundle', ['class' => 'fa fa-newspaper-o']);
    }



    /**
     * {@inheritdoc}
     */
    public function updateMetadata(MediaInterface $media, $force = false)
    {
        $refUrl = $this->getReferenceUrl($media);

        if (!preg_match('/https?\:\/\/prezi\.com\/view\/([a-z0-9]+)/i', $refUrl, $matches)) {
            throw new \RuntimeException('Unable to parse the Prezi url:' . $refUrl);
        }

        try {
            $metadata = $this->getMetadata($media, 'https://prezi.com/view/'.$matches[1].'/');
        } catch (\RuntimeException $e) {
            $media->setEnabled(false);
            $media->setProviderStatus(MediaInterface::STATUS_ERROR);

            return;
        }

        // store provider information
        $media->setProviderMetadata($metadata);

        // update Media common fields from metadata
        if ($force) {
            $media->setName($metadata['title']);
            $media->setDescription($metadata['description']);
        }

        $media->setHeight($metadata['height']);
        $media->setWidth($metadata['width']);
    }

    /**
     * {@inheritdoc}
     */
    public function getDownloadResponse(MediaInterface $media, $format, $mode, array $headers = [])
    {
        return new RedirectResponse($this->getReferenceUrl($media), 302, $headers);
    }

    /**
     * Get provider reference url.
     *
     * @param MediaInterface $media
     *
     * @return string
     */
    public function getReferenceUrl(MediaInterface $media)
    {
        return sprintf('http://prezi.com/view/%s', $media->getProviderReference());
    }

    /**
     * @param MediaInterface $media
     */
    protected function fixBinaryContent(MediaInterface $media)
    {
        if (!$media->getBinaryContent()) {
            return;
        }

        if (preg_match("/prezi\.com\/(view\/)([a-z0-9]+)/i", $media->getBinaryContent(), $matches)) {
            $media->setBinaryContent($matches[2]);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doTransform(MediaInterface $media)
    {
        $this->fixBinaryContent($media);

        if (!$media->getBinaryContent()) {
            return;
        }

        // store provider information
        $media->setProviderName($this->name);
        $media->setProviderReference($media->getBinaryContent());
        $media->setProviderStatus(MediaInterface::STATUS_OK);

        $this->updateMetadata($media, true);
    }

    protected function getMetadata(MediaInterface $media, $url)
    {
        try {
            $response = $this->browser->get($url, ['Accept' => 'text/html;charset=UTF-8']);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('Unable to retrieve the video information for :'.$url, null, $e);
        }

        $doc = new \DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($response->getContent(), 'HTML-ENTITIES', "UTF-8"));

        $xpath = new \DOMXPath($doc);
        $filtered = $xpath->query("//meta[@property='og:image']");
        $ogImage = $filtered->item(0)->getAttribute('content');

        $filtered = $xpath->query("//meta[@property='og:title']");
        $ogTitle = $filtered->item(0)->getAttribute('content');

        $filtered = $xpath->query("//meta[@property='og:description']");
        $ogDescription = $filtered->item(0)->getAttribute('content');

        $media_sizes = getimagesize($ogImage);

        return [
            'image' => $ogImage,
            'title' => $ogTitle,
            'description' => $ogDescription,
            'width' => $media_sizes[0],
            'height' => $media_sizes[1],
        ];
    }
}
