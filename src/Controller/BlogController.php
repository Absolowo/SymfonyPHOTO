<?php

namespace App\Controller;

use Symfony\Component\Filesystem\Filesystem;
use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\Image;
use App\Form\CommentFormType;
use App\Form\PostFormType;
use App\Form\ImageFormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class BlogController extends AbstractController
{
    #[Route("/blog/buscar/{page}", name: 'blog_buscar')]
    public function buscar(ManagerRegistry $doctrine,  Request $request, int $page = 1): Response
    {
    $repository = $doctrine->getRepository(Post::class);
    $searchTerm = $request->query->get('searchTerm', '');
    $posts = $repository->findByTextPaginated($page, $searchTerm);
    $recents = $repository->findRecents();
    return $this->render('blog/blog.html.twig', [
        'posts' => $posts,
        'recents' => $recents,
        'searchTerm' => $searchTerm
    ]);
    }
   
    #[Route("/blog/new", name: 'new_post')]
    public function newPost(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $posts = $repository->findAll();
        $this->denyAccessUnlessGranted("ROLE_USER");
        $post = new Post();
        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $image = $form->get('Image')->getData();
            if ($image) {
                $originalFilename = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$image->guessExtension();
        
                try {
                    
                    $image->move(
                        $this->getParameter('images_directory'), $newFilename
                    );
                    $filesystem = new Filesystem();
                    $filesystem->copy(
                            $this->getParameter('images_directory') . '/'. $newFilename, 
                            $this->getParameter('portfolio_directory') . '/'.  $newFilename, true);
                   
                } catch (FileException $e) {
                    
                }
        
                $post->setImage($newFilename);
            }
            $post = $form->getData();
            $post->setSlug($slugger->slug($post->getTitle()));
            $post->setUser($this->getUser());
            $post->setNumLikes(0);
            $post->setNumComments(0);
            $post->setNumViews(0);
            $entityManager = $doctrine->getManager();
            $entityManager->persist($post);
            $entityManager->flush();
            return $this->redirectToRoute('single_post', ["slug" => $post->getSlug()]);
        }
        return $this->render('blog/new_post.html.twig', array(
            'form' => $form->createView(),
            'post' => $posts
        ));
    }

    #[Route("/single_post/{slug}/like", name: 'post_like')]
    public function like(ManagerRegistry $doctrine, $slug): Response
{
    $repository = $doctrine->getRepository(Post::class);
    $post = $repository->findOneBy(["Slug"=>$slug]);
    $this->denyAccessUnlessGranted("ROLE_USER");
    if ($post){
        $post->setNumLikes($post->getNumLikes() + 1);
        $entityManager = $doctrine->getManager();    
        $entityManager->persist($post);
        $entityManager->flush();
    }
    return $this->redirectToRoute('single_post', ["slug" => $post->getSlug()]);

}

    #[Route("/blog/{page}", name: 'blog')]
    public function index(ManagerRegistry $doctrine, int $page = 1): Response
    {
    $repository = $doctrine->getRepository(Post::class);
    $posts = $repository->findAllPaginated($page);
    $recents = $repository->findRecents();

    return $this->render('blog/blog.html.twig', [
        'posts' => $posts,
        'recents' => $recents,
    ]);
    }

    #[Route('/single_post/{slug}', name: 'single_post')]
    public function post(ManagerRegistry $doctrine, Request $request, $slug): Response
    {
    $repository = $doctrine->getRepository(Post::class);
    $post = $repository->findOneBy(["Slug"=>$slug]);
    $recents = $repository->findRecents();
    $comment = new Comment();
    $form = $this->createForm(CommentFormType::class, $comment);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
        $comment = $form->getData();
        $comment->setPost($post);  
        $post->setNumComments($post->getNumComments() + 1);
        $entityManager = $doctrine->getManager();    
        $entityManager->persist($comment);
        $entityManager->flush();
        return $this->redirectToRoute('single_post', ["slug" => $post->getSlug()]);
    }
    return $this->render('blog/single_post.html.twig', [
        'post' => $post,
        'recents' => $recents,
        'commentForm' => $form->createView()
    ]);
}
}
