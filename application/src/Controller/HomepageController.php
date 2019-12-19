<?php

namespace App\Controller;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HomepageController extends AbstractController
{
    private $mailer;
    private $httpClient;
    private $cache;

    public function __construct(MailerInterface $mailer, HttpClientInterface $httpClient, CacheItemPoolInterface $cache)
    {
        $this->mailer = $mailer;
        $this->httpClient = $httpClient;
        $this->cache = $cache;
    }

    /**
     * @Route("/", name="homepage")
     */
    public function index()
    {
        $mail = (new Email())
            ->to('foo@example.com')
            ->from('foo@example.com')
            ->subject('new email')
            ->text('some content')
        ;
        $this->mailer->send($mail);

        $esStatus = $this->httpClient->request('GET', 'http://elasticsearch:9200')->toArray();

        $visitCountItem = $this->cache->getItem('visitCount');
        if ($visitCountItem->isHit()) {
            $visitCount = $visitCountItem->get();
        } else {
            $visitCount = 0;
        }
        $visitCountItem->set(++$visitCount);
        $this->cache->save($visitCountItem);


        return $this->render('homepage/index.html.twig', [
            'esStatus' => $esStatus,
            'visitCount' => $visitCount - 1,
        ]);
    }
}
