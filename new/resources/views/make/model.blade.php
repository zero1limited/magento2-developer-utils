{!! '<?php' !!}

{{ $doc_header }}
namespace {!! $namespace !!};

@isset($class_interface)
use {{ $class_interface }} as {{ $interface_name }};
@endisset
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
@isset($resource_model)
use {{ $resource_model }} as ResourceModel;
@endisset
@isset($class_validator)
use {{ $class_validator }} as Validator;
use Magento\Framework\Validation\ValidationException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb as AbstractDbCollection;
@endisset

/**
 * {{ $class_name }} model
 */
class {{ $class_name }} extends AbstractModel implements @isset($interface_name){{ $interface_name }}@endisset, IdentityInterface
{
    public const CACHE_TAG = '{{ $cache_tag }}';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = '{{ $cache_tag }}';

@isset($class_validator)
    public function __construct(
        protected Validator $validator,
        Context $context,
        Registry $registry,
        ?AbstractResource $resource = null,
        ?AbstractDbCollection $resourceCollection = null,
        array $data = []
    ){
        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
    }
    @endisset

    /**
     * Construct.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }

    /**
     * Get identities
     *
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

@foreach ($class_fields as $field)
    /**
     * Set {{ $field['id'] }}
     *
     * @param {{ $field['type'] }} ${{ $field['id'] }}
     * @return self
     */
    public function set{{ $field['method_suffix'] }}({{ $field['type'] }} ${{ $field['id'] }})
    {
        return $this->setData({!! $field['accessor_key'] !!}, ${{ $field['id'] }});
    }

    /**
     * Get {{ $field['id'] }}
     *
     * @return {{ $field['type'] }}
     */
    public function get{{ $field['method_suffix'] }}()
    {
        return $this->getData({!! $field['accessor_key'] !!});
    }

@endforeach
        
    /**
     * {!! '@return' !!} AbstractModel
     * @if( $class_validator ){!! '@throws' !!} \Magento\Framework\Validation\ValidationException
@endif
     */
    public function beforeSave()
    {
        if ($this->hasDataChanges()) {
            $this->setUpdateTime(null);
        }

@if( $class_validator )
        $thhis->validateBeforeSave()
        $validationResult = $this->validator->validate($this);
        if (!$validationResult->isValid()) {
            throw new ValidationException(__('Validation Failed'), null, 0, $validationResult);
        }

@endif
        return parent::beforeSave();
    }
}
