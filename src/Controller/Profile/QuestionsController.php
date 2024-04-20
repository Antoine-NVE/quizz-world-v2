<?php

namespace App\Controller\Profile;

use App\Entity\Questions;
use App\Form\QuestionsFormType;
use App\Repository\QuestionsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profil/questions', name: 'profile_questions_')]
class QuestionsController extends AbstractController
{
    #[Route('/{slug}/{difficulty}/{number}', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        string $slug,
        string $difficulty,
        int $number,
        QuestionsRepository $questionsRepository,
        EntityManagerInterface $manager,
        Request $request
    ): Response {
        $question = $questionsRepository->findWithPropositionsBySlugDifficultyNumberAndUser($slug, $difficulty, $number, $this->getUser());
        // dd($question);
        if (!$question) throw $this->createNotFoundException('Aucune question trouvée.');
        $propositions = $question->getPropositions();

        $form = $this->createForm(QuestionsFormType::class, $question);

        for ($i = 0; $i < 4; $i++) {
            // On récupère la string "proposition" de l'objet
            $proposition = $propositions[$i]->getProposition();

            // On remplit les inputs
            $form->get('p' . $i + 1)->setData($proposition);

            // Si la string correspond à la bonne réponse, on set l'input
            if ($proposition === $question->getAnswer()) {
                $form->get('answer')->setData('a' . $i + 1);
            }
        }

        try {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Ce tableau va permettre de choisir dans quel ordre récupérer les valeurs rentrées
                $order = [1, 2, 3, 4];
                shuffle($order);

                for ($i = 0; $i < 4; $i++) {
                    // On vérifie qu'il n'y ait pas de doublon
                    for ($j = 0; $j < 4; $j++) {
                        if (($i !== $j) && ($form->get('p' . $order[$i])->getData() === $form->get('p' . $order[$j])->getData())) {
                            throw new \Exception('Certaines propositions sont présentes plusieurs fois.');
                        }
                    }

                    // On récupère la proposition de l'input
                    $proposition = $form->get('p' . $order[$i])->getData();

                    // On set
                    $propositions[$i]->setProposition($proposition);

                    // Si c'est la bonne réponse, on modifie l'objet
                    if ($form->get('answer')->getData() === 'a' . $order[$i]) {
                        $question->setAnswer($proposition);

                        $manager->persist($question);
                    }

                    $manager->persist($propositions[$i]);
                }

                $manager->flush();
                $this->addFlash('success', 'La question a bien été modifiée.');
                return $this->redirectToRoute('profile_questionnaires_index', ['slug' => $question->getQuestionnaire()->getCategory()->getSlug(), 'difficulty' => $question->getQuestionnaire()->getDifficulty()]);
            }
        } catch (\Throwable $th) {
            $this->addFlash('danger', 'Une erreur est survenue.');
        }

        return $this->render('profile/questions/edit.html.twig', compact('form', 'question'));
    }

    #[Route('/{id}', name: 'remove', methods: ['DELETE'])]
    public function remove(int $id, QuestionsRepository $questionsRepository, EntityManagerInterface $manager): Response
    {
        $question = $questionsRepository->findOneByIdAndUser($id, $this->getUser());
        if (!$question) throw $this->createNotFoundException('Question non trouvée.');
        $slug = $question->getQuestionnaire()->getCategory()->getSlug();
        $difficulty = $question->getQuestionnaire()->getDifficulty();

        try {
            $manager->remove($question);

            $manager->flush();
            $this->addFlash('success', 'La question a bien été supprimée.');
        } catch (\Throwable $th) {
            $this->addFlash('danger', 'Une erreur est survenue.');
        }

        return $this->redirectToRoute('profile_questionnaires_index', compact('slug', 'difficulty'));
    }
}
