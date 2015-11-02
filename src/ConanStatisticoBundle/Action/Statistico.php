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

                case 'graph':
                    $this->graphAction(
                        $request->get('bucket'),
                        $request->get('type'),
                        $request->get('from', '-30 minutes')
                    );
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
        /** @var Reader $reader */
        $reader = $this->getConfig()->get('statistico.reader');

        $buckets = $this->findBuckets($request->get('q'));

        $types = [];
        foreach ($buckets as $bucket) {
            $types[$bucket] = $reader->getAvailableTypes($bucket);
        }

        throw new DirectResponseException(new JsonResponse(['buckets' => $buckets, 'types' => $types]));
    }

    /**
     * @param Request $request
     * @param $bucket
     *
     * @return array
     *
     * @throws DirectResponseException
     */
    public function graphAction($bucket, $type, $from)
    {
        /** @var Reader $reader */
        $reader = $this->getConfig()->get('statistico.reader');

        $types = $type ? [$type] : $reader->getAvailableTypes($bucket);
        $from = new \DateTime($from);
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

        if (in_array('gauges', $types)) {
            $counts = $reader->queryGauges($bucket, $granularity, $from, $to);
            $counts = $this->completeGaugesData($counts, $factor, $from, $to);
        } elseif (in_array('counts', $types)) {
            $counts = $reader->queryCounts($bucket, $granularity, $from, $to);
            $counts = $this->completeCountsData($counts, $factor, $from, $to);
        }

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
        list ($min, $max) = $this->getMinMax($data, $factor, $from, $to);

        $retval = [];

        for ($t = $min; $t <= $max; $t += $factor) {
            $retval[$t] = isset($data[$t]) ? $data[$t] : 0;
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
    protected function completeGaugesData(
        array $data,
        $factor,
        \DateTime $from,
        \DateTime $to = null
    ) {
        list ($min, $max) = $this->getMinMax($data, $factor, $from, $to);

        $retval = [];

        $previous = 0;

        for ($t = $min; $t <= $max; $t += $factor) {
            $retval[$t] = isset($data[$t]) ? $data[$t] : $previous;

            $previous = $retval[$t];
        }

        return $retval;
    }

    /**
     * @param array $data
     * @param $factor
     * @param \DateTime $from
     * @param \DateTime|null $to
     *
     * @return array
     */
    protected function getMinMax(
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

        return [$min, $max];
    }
}
