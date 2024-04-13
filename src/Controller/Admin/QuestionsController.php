<?php

namespace App\Controller\Admin;

use App\Entity\Questions;
use App\Form\QuestionsFormType;
use App\Repository\QuestionsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/questions', name: 'admin_questions_')]
class QuestionsController extends AbstractController
{
    #[Route('/{id}', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, QuestionsRepository $questionsRepository, EntityManagerInterface $manager, Request $request): Response
    {
        $question = $questionsRepository->findWithPropositions($id);
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

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            for ($i = 0; $i < 4; $i++) {
                // On récupère la proposition de l'input
                $proposition = $form->get('p' . $i + 1)->getData();

                // On set
                $propositions[$i]->setProposition($proposition);

                // Si c'est la bonne réponse, on modifie l'objet
                if ($form->get('answer')->getData() === 'a' . $i + 1) {
                    $question->setAnswer($proposition);

                    $manager->persist($question);
                }

                $manager->persist($propositions[$i]);
            }

            $manager->flush();

            $this->addFlash('success', 'La question a bien été modifiée.');
            return $this->redirectToRoute('admin_questionnaires_index', ['id' => $question->getQuestionnaire()->getId()]);
        }

        return $this->render('admin/questions/edit.html.twig', compact('form', 'question'));
    }

    #[Route('/{id}', name: 'remove', methods: ['DELETE'])]
    public function remove(Questions $question, EntityManagerInterface $manager): Response
    {
        $questionnaireId = $question->getQuestionnaire()->getId();

        $manager->remove($question);
        $manager->flush();

        $this->addFlash('success', 'La question a bien été supprimée.');
        return $this->redirectToRoute('admin_questionnaires_index', ['id' => $questionnaireId]);
    }
}
