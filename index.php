<?php
/**
 * Http gateway for files from minter blockchain
 * @license     LGPL-3.0
 * @link        https://github.com/counters/minter-file-symfony-gateway
 * @copyright   Webcounters <webcounters@gmail.com>
 * @author      Webcounters <webcounters@gmail.com>
 */

use App\CID\HashAlgorithm;
use App\CID\MtBase58;
use App\CID\ObjCIDv0;
use App\Minter\Block;
use App\Minter\Transaction;
use App\Minter\Utils\DateTimeHelper;
use App\MultiHttp\MultiHttp;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

require __DIR__ . '/vendor/autoload.php';


class Kernel extends BaseKernel
{
    use MicroKernelTrait;

//    private $XProjectId = '';
//    private $XProjectSecret = '';
    public const UseMultiCurl = true;
    private $minterApi = 'http://127.0.0.1:8841/';
    /**
     * @var Transaction
     */
    private $transactionClass;
    private $allowedRecipients = [''];


    public function registerBundles()
    {
        return [
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle()
        ];
    }

    public function phpinfo()
    {
        phpinfo();
    }

    public function indexController()
    {
        return new Response("");
    }

    public function cidv0Controller($trs, Request $request)
    {
        $transactions = null;
        $urls = null;
        $contentTrx = null;
        $payloads = null;
        $error = 0;

        $statusCode = null;
        $response = new Response();

        if (($indexHashTransaction = $this->isHashTransaction($trs))) {

            $configGuzzleHttp = [
                'base_uri' => $this->minterApi,
                'connect_timeout' => 30.0,
                'timeout' => 60.0,
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ];
            if (isset($this->XProjectId)) {
                $configGuzzleHttp['headers'] = array_merge($configGuzzleHttp['headers'], ['X-Project-Id' => $this->XProjectId, 'X-Project-Secret' => $this->XProjectSecret]);
            }
            $httpClient = new \GuzzleHttp\Client($configGuzzleHttp);
            $this->transactionClass = new Transaction($httpClient);
            $blockClass = new Block($httpClient);


            $resultTrs = $this->transactionClass->getResult($indexHashTransaction);

            $payloadObj = null;
            if ($resultTrs) {
                $resultBlock = $blockClass->getResult($resultTrs->height);
                if ($resultBlock) {
                    $dateTimeHelper = new DateTimeHelper();
                    $lastModified = $dateTimeHelper->parse($resultBlock->time);
                } else {
                    $lastModified = new DateTime();
                }
                $payloadObj = json_decode(base64_decode($resultTrs->payload));
                $cidv0 = new ObjCIDv0($payloadObj->h);
                $response->setEtag($cidv0->toString());
                $response->setLastModified($lastModified);
                if ($response->isNotModified($request)) {
                    $response->send();
                    exit;
                }

            } else {
                $error++;
            }

            if (!isset($payloadObj->trs) or !isset($payloadObj->t) or !isset($payloadObj->h)) {
                $response->setStatusCode(404);
                return $response;
            }

            if (($transactions = $this->getTransactionFromArr($payloadObj))) {
                $parts = [];
                $partToTrs = [];
                $urls = [];
                $contentTrx = [];
                foreach ($transactions as $index => $transaction) {
                    $url = $this->minterApi . "transaction?hash=" . $transaction;
                    $urls[] = $url;
                    $contentTrx[] = $transaction;
                    $partToTrs[$index] = $transaction;
                    $parts[$url] = $index;
                }
            }
//
        }
        if (self::UseMultiCurl == false and $contentTrx) {
            $payloads = [];
            foreach ($contentTrx as $index => $transaction) {
                $transaction = $this->transactionClass->getResult($transaction);
                if ($transaction) {
                    if ($transaction->payload == '') {
                        $payloads[$index] = '';
                    } else {
                        $payloads[$index] = base64_decode($transaction->payload);
                    }
                } else {
                    $error++;
                }

            }
        }

        if (self::UseMultiCurl == true and $urls) {
            if (isset($this->XProjectId)) {
                $headers = ['X-Project-Id' => $this->XProjectId, 'X-Project-Secret' => $this->XProjectSecret];
            } else {
                $headers = null;
            }
            $multiHttp = new MultiHttp($headers);

            $payloads = [];
            $multiHttp->run($urls, function ($url, $content, $curl_status, $ch) use (&$payloads, &$error, $parts, $partToTrs) {
                if (!$curl_status) {
                    //                    print "Load tx from ".$url."/n";
                    $index = $parts[$url];
                    $tmpHashTransaction = $partToTrs[$index];
                    $transaction = $this->transactionClass->validate(json_decode($content));
                    if ($transaction) {
                        if ($transaction->payload == '') {
                            $payloads[$index] = '';
                        } else {
                            $payloads[$index] = base64_decode($transaction->payload);
                        }
                    } else {
                        $error++;
                    }
                } else {
                    //                    print "! Error load tx from ".$url."/n";
                    $error++;
                }
            });
        }
        if ($error === 0 and $payloads) {
            $mime_type = filter_var($payloadObj->t);
            $content = "";
            for ($i = 0; $i < count($payloads); $i++) {
                if (isset($payloads[$i])) {
                    $content .= $payloads[$i];
                } else {
                    $statusCode = 503;
                    $error++;
                }
            }
            $contentHash = hash(HashAlgorithm::get($cidv0->getFnCode()), $content);
            if ($contentHash == $cidv0->getHashDigest()) {
                $response->setStatusCode(200);
                $response->setContent($content);
                $response->headers->set('Content-Type', $mime_type);
                $response->setCache([
                    'etag' => $cidv0->toString(),
                    'last_modified' => $lastModified,
                    'max_age' => 31536000,
                    's_maxage' => 31536000,
                    'private' => false,
                    'public' => true,
                    'immutable' => true,
                ]);

                $dateExpires = new DateTime();
                $dateExpires->modify('+10 years');
                $response->setExpires($dateExpires);


            } else {
                $error++;
            }

        }
        if ($statusCode) {
            $response->setStatusCode($statusCode);
        } elseif ($error != 0) {
            $response->setStatusCode(404);
        }
        return $response;
    }

    /**
     * @param string $trs
     * @return bool
     */
    private function isHashTransaction($trs)
    {
        return filter_var($trs, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => "/^Mt[0-9a-fA-F]{64}$/"]]);
    }

    /**
     * @param $payloadObj
     * @return string[]|null
     */
    private function getTransactionFromArr(&$payloadObj)
    {
        if (isset($payloadObj->trs)) {
            if (is_array($payloadObj->trs)) {
                $mtBase58 = new MtBase58();
                $transactions = [];
                foreach ($payloadObj->trs as $itemTrs) {
                    if (($hashTransaction = $this->isHashTransaction($mtBase58->getTransaction($itemTrs)))) {
                        $transactions[] = $hashTransaction;
                    } else {
                        return null;
                    }
                }
                return $transactions;
            }
        }
        return null;
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        // PHP equivalent of config/packages/framework.yaml
        $c->loadFromExtension('framework', [
            'secret' => 'SECRET'
        ]);
    }

    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        $routes->add('/', 'kernel::indexController');
//        $routes->add('/phpinfo', 'kernel::phpinfo');
        $routes->add('/{trs}', 'kernel::cidv0Controller');
    }
}


//$kernel = new Kernel('dev', true);
$kernel = new Kernel('prod', false);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);