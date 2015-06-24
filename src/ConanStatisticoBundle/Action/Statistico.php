<?php

namespace TreeHouse\ConanStatisticoBundle\Action;

use Fieg\Statistico\Reader;
use FM\ConanBundle\Action\CustomAction;
use FM\ConanBundle\Templating\Helper\ActionHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use TreeHouse\ConanStatisticoBundle\Exception\DirectResponseException;

class Statistico extends CustomAction
{
    /**
     * @inheritdoc
     */
    public function execute(Request $request)
    {
        $this->request = $request;

        /** @var ActionHelper $helper */
        $helper = $this->getConfig()->get('fm_conan.templating.action_helper');

        $this->targetPath = $request->get('target_path', $helper->getRouteByAction($this->getActionConfig()));

        if ($action = $request->get('action')) {
            switch ($action) {
                case 'search':
                    $this->searchAction($request);
                    break;

                case 'data':
                    $this->dataAction($request);
                    break;
            }
        }

        return parent::execute($request);
    }

    /**
     * @inheritdoc
     */
    public function getTemplateName()
    {
        return 'TreeHouseConanStatisticoBundle::statistico.html.twig';
    }

    /**
     * @param Request $request
     *
     * @throws DirectResponseException
     */
    private function searchAction(Request $request)
    {
        $buckets = $this->findBuckets($request->get('q'));

        throw new DirectResponseException(new JsonResponse(['buckets' => $buckets]));
    }

    /**
     * @param Request $request
     *
     * @throws DirectResponseException
     */
    private function dataAction(Request $request)
    {
        /** @var Reader $reader */
        $reader = $this->getConfig()->get('statistico.reader');

        $counts = $reader->queryCounts($request->get('bucket'), 'minutes', new \DateTime('-24 hours'));

        $retval = [];
        foreach ($counts as $timestamp => $count) {
            $retval[] = ['date' => $timestamp, 'count' => $count];
        }

        throw new DirectResponseException(new JsonResponse($retval));
    }

    /**
     * @param $query
     *
     * @return string[]
     */
    private function findBuckets($query)
    {
        /** @var Reader $reader */
        $reader = $this->getConfig()->get('statistico.reader');

        $retval  = [];
        $buckets = $reader->getBuckets();

        if (!trim($query)) {
            return $buckets;
        }

        foreach ($buckets as $bucket) {
            if (false !== stripos($bucket, $query)) {
                $retval[] = $bucket;
            }
        }

        return $retval;
    }
}
