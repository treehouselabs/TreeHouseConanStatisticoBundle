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

        $from = new \DateTime($request->get('from', '-30 minutes'));
        $to = new \DateTime();
        $diff = $to->getTimestamp() - $from->getTimestamp();

        if ($diff <= 3600) {
            $granularity = 'seconds';
            $factor = 1;
        } elseif ($diff <= 86400) {
            $granularity = 'minutes';
            $factor = 60;
        } else {
            $granularity = 'hours';
            $factor = 3600;
        }

        $counts = $reader->queryCounts($request->get('bucket'), $granularity, $from, $to);
        $counts = $this->completeCountsData($counts, $factor, $from, $to);

        $retval = [
            'series' => [],
            'from' => $from->getTimestamp(),
            'to' => (new \DateTime())->getTimestamp(),
        ];

        foreach ($counts as $timestamp => $count) {
            $retval['series'][] = ['date' => (string) $timestamp, 'count' => $count];
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

    /**
     * @param array $data
     * @param int $factor
     * @param \DateTime $from
     * @param \DateTime $to
     *
     * @return array
     */
    protected function completeCountsData(
        array $data,
        $factor,
        \DateTime $from,
        \DateTime $to = null
    ) {
        reset($data);

        $to = $to ?: new \DateTime();

        // factor diff
        $mod = ($from->getTimestamp() % $factor);

        if ($data) {
            $min = min($from->getTimestamp() - $mod, key($data)); // first key
        } else {
            $min = $from->getTimestamp();
        }

        $max = $to->getTimestamp();

        $retval = [];

        for ($t = $min; $t <= $max; $t += $factor) {
            $retval[$t] = isset($data[$t]) ? $data[$t] : 0;
        }

        return $retval;
    }
}
