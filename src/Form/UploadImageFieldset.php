<?php

namespace UploadImages\Form;

use Blog\Entity\Category;
use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
use Doctrine\Persistence\ObjectManager;
use DoctrineModule\Form\Element\ObjectMultiCheckbox;
use DoctrineModule\Form\Element\ObjectSelect;
use Laminas\Form\Element\File;
use Laminas\Form\Element\Hidden;
use Laminas\Form\Element\Text;
use Laminas\Form\Fieldset;
use Laminas\InputFilter\InputFilterProviderInterface;
use UploadImages\Entity\Image;

class UploadImageFieldset extends Fieldset implements InputFilterProviderInterface
{
    public function __construct(ObjectManager $objectManager)
    {
        parent::__construct('upload-image');

        $this->setHydrator(new DoctrineHydrator($objectManager))
            ->setObject(new Image());

        $this->add([
            'type'  => File::class,
            'name' => 'image',
            'options' => [
                'label' => 'Upload afbeelding',
            ],
            'attributes' => [
                'multiple' => 'multiple',
                'class' => 'd-none'
            ],
        ]);

        $this->add([
            'type'  => Text::class,
            'name' => 'nameImage',
            'options' => [
                'label' => 'Afbeelding naam',
            ],
            'attributes' => [
                'class' => 'form-control',
            ],
        ]);

        $this->add([
            'type'  => Text::class,
            'name' => 'alt',
            'options' => [
                'label' => 'Alt tekst',
            ],
            'attributes' => [
                'class' => 'form-control',
            ],
        ]);

        $this->add([
            'type'  => Text::class,
            'name' => 'descriptionImage',
            'options' => [
                'label' => 'Omschrijving',
            ],
            'attributes' => [
                'class' => 'form-control',
            ],
        ]);

    }

    public function getInputFilterSpecification()
    {
        return [
            'categories' => [
                'required' => false,
            ]
        ];
    }
}