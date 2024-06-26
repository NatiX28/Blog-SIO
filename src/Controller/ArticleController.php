<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use App\Service\Slugger;
use Doctrine\ORM\EntityManagerInterface;
use phpDocumentor\Reflection\DocBlock\Tags\See;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\SecurityBundle\Security;


#[Route('/article')]
class ArticleController extends AbstractController
{
    #[Route('/', name: 'app_article_index', methods: ['GET'])]
    public function index(ArticleRepository $articleRepository): Response
    {
        return $this->render('article/index.html.twig', [
            'articles' => $articleRepository->findAll(),
        ]);
    }

    public function __construct(
        private Security $security,
    ){
    }

    #[Route('/new', name: 'app_article_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $article = new Article();
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slug = new Slugger();
            $article->setSlug($slug->slugify($article->getTitre()));
            $article->setUtilisateur($this->security->getUser());
            $image = $form->get('image')->getData();

            if($image){
                $originalFilename = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slug->slugify($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$image->guessExtension();

                try{
                    $image->move(
                        $this->getParameter('articles_images_directory'),
                        $newFilename
                    );
                } catch(FileException $e){
                    console.log("Error - ".$e->getMessage()); 
                }

                $article->setImage("/images/articles/".$newFilename);
            }

            //$article->setContenu($form->get('contenu')->getData());

            $entityManager->persist($article);
            $entityManager->flush();

            return $this->redirectToRoute('app_article_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('article/new.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }

    #[Route('show/{slug}', name: 'app_article_show', methods: ['GET'])]
    public function show(Article $article): Response
    {
        return $this->render('article/show.html.twig', [
            'article' => $article,
        ]);
    }

  
    #[Route('/{id}/edit', name: 'app_article_edit',  methods: ['GET', 'POST'])]
    public function edit(Request $request, Article $article, EntityManagerInterface $entityManager): Response
    {   
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_article_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('article/edit.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_article_delete', methods: ['POST', 'GET'])]
    public function delete(Request $request, Article $article, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$article->getId(), $request->request->get('_token'))) {
            $entityManager->remove($article);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_article_index', [], Response::HTTP_SEE_OTHER);
    }
    

    public function recentArticles(ArticleRepository $articleRepository): Response
    {
        return $this->render('article/recent.html.twig', [
            'articles' => $articleRepository->findRecent(),
        ]);
    }
    

    #[Route('/search', name: 'app_article_search', methods: ['GET'])]
    public function search(Request $request) : Response
    {
        $form = $this->createFormBuilder(null,[
            'attr' => ['class' => 'form-inline'],
        ])
        ->setAction($this->generateUrl('app_article_result'))
        ->add('search',TextType::class, [
            'label' => false,
            'attr' => ['placeholder' => 'Rechercher...', 
                       'class' => 'form-control mr-sm-2']
        ])
        ->add('valider', SubmitType::class,[
            'attr' => [ 'class' => 'btn btn-outline-success my-2 my-sm-0']
        ])
        ->setMethod('GET')
        ->getForm();

        return $this->render('article/search_form.html.twig', [
            'form' => $form->createView()   
        ]);
    }

    #[Route('/result', name: 'app_article_result', methods: ['GET'])]
    public function result(ArticleRepository $articleRepository, Request $request) : Response
    {
        $form = ($request->get('form'));
        $search = $form['search'];
        $article = $articleRepository->findBySearch($search);
        return $this->render('article/index.html.twig', [
            'articles' => $article
        ]);
    }

    #[Route('/api/{id}', name: 'app_article_api', methods: ['GET'])]
    public function serialize(Article $article, SerializerInterface $serializer) : JsonResponse
    {

        $jsonContent = $serializer->serialize($article, 'json', ['groups' => 'article']);
        $response =  new JsonResponse($jsonContent);
        
        return $response;
    }
    
}
