<?php

namespace App\AdminBundle\Admin\User;

use App\AdminBundle\Admin\AbstractAdmin;
use FOS\UserBundle\Model\UserManagerInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

class BaseUserAdmin extends AbstractAdmin
{
    protected $userManager;

    /**
     * {@inheritdoc}
     */
    public function getFormBuilder()
    {
        $this->formOptions['data_class'] = $this->getClass();

        $options = $this->formOptions;
        $options['validation_groups'] = (!$this->getSubject() || is_null($this->getSubject()->getId())) ? 'Registration' : 'Profile';

        $formBuilder = $this->getFormContractor()->getFormBuilder($this->getUniqid(), $options);

        $this->defineFormBuilder($formBuilder);

        return $formBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getExportFields()
    {
        // avoid security field to be exported
        return array_filter(parent::getExportFields(), function ($v) {
            return !in_array($v, array('password', 'salt'));
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('username')
            ->add('email')
            ->add('groups')
            ->add('enabled', null, array('editable' => true))
            ->add('locked', null, array('editable' => true))
            ->add('createdAt')
        ;

        if ($this->isGranted('ROLE_ALLOWED_TO_SWITCH')) {
            $listMapper
                ->add('impersonating', 'string', array('template' => 'SonataUserBundle:Admin:Field/impersonating.html.twig'))
            ;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configureDatagridFilters(DatagridMapper $filterMapper)
    {
        $filterMapper
            ->add('id')
            ->add('username')
            ->add('locked')
            ->add('email')
            ->add('groups')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function configureShowFields(ShowMapper $showMapper)
    {
        $showMapper
            ->with('General')
                ->add('username')
                ->add('email')
            ->end()
            ->with('Profile')
                ->add('dateOfBirth')
                ->add('firstname')
                ->add('lastname')
                ->add('website')
                ->add('biography')
                ->add('gender')
                ->add('locale')
                ->add('timezone')
                ->add('phone')
            ->end()
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function configureFormFields(FormMapper $formMapper)
    {
        $container = $this->getConfigurationPool()->getContainer();
        $roles = $container->getParameter('security.role_hierarchy.roles');
        $rolesChoices = self::flattenRoles($roles);

        $formMapper
            ->with('General')
                ->add('username')
                ->add('email')
                ->add('plainPassword', 'password', array(
                    'required' => (!$this->getSubject() || is_null($this->getSubject()->getId())),
                ))
            ->end()
            ->with('Groups')
                ->add('groups', null, array(
                    'required' => false,
                    'expanded' => true,
                    'multiple' => true,
                ))
            ->end()
            ->with('Profile')
                ->add('dateOfBirth', 'birthday', array('required' => false))
                ->add('firstname', null, array('required' => false))
                ->add('lastname', null, array('required' => false))
                ->add('website', 'url', array('required' => false))
                ->add('biography', 'text', array('required' => false))
                ->add('gender', 'sonata_user_gender', array(
                    'required'           => true,
                    'translation_domain' => $this->getTranslationDomain(),
                ))
                ->add('locale', 'locale', array('required' => false))
                ->add('timezone', 'timezone', array('required' => false))
                ->add('phone', null, array('required' => false))
            ->end()
        ;

        if ($this->getSubject() && !$this->getSubject()->hasRole('ROLE_SUPER_ADMIN')) {
            $formMapper
                ->with('Management')
                    ->add('realRoles', 'choice', array(
                        'label'    => 'Rôles',
                        'choices'  => $rolesChoices,
                        'multiple' => true,
                        'required' => false,
                    ))
                    ->add('locked', null, array('required' => false))
                    ->add('enabled', null, array('required' => false))
                ->end()
            ;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function preUpdate($user)
    {
        $this->getUserManager()->updateCanonicalFields($user);
        $this->getUserManager()->updatePassword($user);
    }

    /**
     * @param UserManagerInterface $userManager
     */
    public function setUserManager(UserManagerInterface $userManager)
    {
        $this->userManager = $userManager;
    }

    /**
     * @return UserManagerInterface
     */
    public function getUserManager()
    {
        return $this->userManager;
    }

    /**
     * Turns the role's array keys into string <ROLES_NAME> keys.
     */
    protected static function flattenRoles($rolesHierarchy)
    {
        $flatRoles = array();
        foreach ($rolesHierarchy as $roles) {
            if (empty($roles)) {
                continue;
            }

            foreach ($roles as $role) {
                if (!isset($flatRoles[$role]) && $role !== 'ROLE_USER') {
                    $flatRoles[$role] = $role;
                }
            }
        }

        return $flatRoles;
    }

    protected function getUserGroup()
    {
        $group = $this->get('doctrine')
            ->getRepository('AppUserBundle:Group')
            ->findOneBy(array(
                'name' => $this->getLabel(),
            ))
        ;

        return $group;
    }

    /**
     * In user _create, pre-set Group depending on type of
     * the created user. e.g. Coachs, Providers or Individuals.
     * @return [type] [description]
     */
    public function getNewInstance()
    {
        $coach = parent::getNewInstance();
        $group = $this->getUserGroup();

        $coach->addGroup($group);
        $coach->setEnabled(true);

        return $coach;
    }

    /**
     * Pre-filter lists depending on Group.
     * e.g. In Provider List get only users with group = Providers
     */
    public function getFilterParameters()
    {
        $filterByGroup = ['groups' => ['value' => $this->getUserGroup() ? $this->getUserGroup()->getId() : '']];
        $this->datagridValues = array_merge($filterByGroup, $this->datagridValues);

        return parent::getFilterParameters();
    }
}
