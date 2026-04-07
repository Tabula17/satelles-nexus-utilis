<?php

namespace Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Request;

use Closure;
use Tabula17\Satelles\Nexus\Utilis\Exception\UnexpectedValueException;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Response\Base;
use Tabula17\Satelles\Nexus\Utilis\Server\Protocol\Status;
use Tabula17\Satelles\Utilis\Config\AbstractDescriptor;

abstract class Payload extends AbstractDescriptor //implements RequestHandlerInterface
{
    /**
     * El payload del mensaje, que contiene la información específica de la acción a realizar.
     * Este descriptor debe ser definido por cada clase concreta que extienda Payload, y su estructura dependerá de los requisitos de cada acción.
     * @var AbstractDescriptor
     */
    abstract AbstractDescriptor $payload {
        get;
    }
    /**
     * La acción específica que debe ejecutarse. Su valor está restringido a un conjunto de acciones válidas definidas
     * dentro del protocolo asociado.
     *
     * @throws UnexpectedValueException Si la acción proporcionada no está dentro del conjunto de acciones válidas del protocolo.
     * @var string
     */
    protected(set) string $action {
        get {
            return $this->action;
        }
        /**
         * @throws UnexpectedValueException
         */
        set(string $action) {
            $actions = $this->protocol->toArray();
            if (!in_array($action, $actions, true)) {
                throw new UnexpectedValueException('Invalid action: ' . $action . '. Must be one of: ' . implode(', ', $actions));
            }
            $this->action = $action;
        }
    }
    /**
     * El protocolo asociado con el payload, que define las acciones y sus restricciones.
     * @var Action
     */
    abstract public Action $protocol
        {
            get;
        }
    /**
     * This property indicates where to store the id of the message to send back to the client.
     * If the client sends a message with an id, it will be stored in this property.
     * If it is null, the id will not be stored in the payload, but it will be generated and stored in the $id property of the Payload class.
     * This property must be defined in the Payload class and must be set to the name of an existing property where the ID will be stored.
     * @var string|null
     */
    abstract protected ?string $idProperty
        {
            get;
        }
    /**
     *
     * @var string|mixed
     */
    private string $id
        {
            get {
                return $this->id;
            }
            set(string $id) {
                $this->id = $id;
                if (isset($this->idProperty) && $this->offsetExists($this->idProperty) && $this->get($this->idProperty) !== $id) {
                    $this->set($this->idProperty, $id);
                }
                //$this->set($this->idProperty, $id);
            }
        }
    /**
     * El estado del mensaje, que indica si la solicitud ha sido procesada correctamente o no.
     * @var Status
     */
    protected(set) Status $status = Status::unknown
        {
            get {
                return $this->status;
            }
        }
    /**
     *
     * @var Closure
     */
    protected Closure $resolver
        {
            get {
                return $this->resolver;
            }
            set(array|string|Closure $resolver) {
                $this->resolver = static::cast($resolver);
            }
        }

    final public function __construct(
        callable|string $resolver,
        ?array          $values = [],
        Action          $protocol = new Action()
    )
    {
        $this->resolver = $resolver;
        $this->protocol = $protocol;
        $this->initialize($values);
        parent::__construct($values);
        $prefix = strtolower($values['action'] ?? 'unknown') . '::';
        $this->id = $values[$this->idProperty ?? ''] ?? uniqid($prefix, true);
    }

    abstract public function initialize(?array &$values): void;

    /**
     * Indica si el payload debe devolver un dataset en la respuesta
     * @return bool
     */
    abstract public function datasetInResponse(): bool;

    /**
     * @param ...$args
     * @return $this
     */
    abstract public function handle(...$args): static;

    abstract public function getResponse(...$args): Base;

    abstract public function getResult(...$args): mixed;

    /**
     * Valida el payload recibido, asegurándose de que cumple con los requisitos necesarios para ser procesado correctamente.
     * @param array $data
     * @return bool
     */
    abstract public static function validatePayload(array $data): bool;

    public function getID(): string
    {
        return $this->id;
    }

    public function getResponseID(): ?string
    {
        return $this->{$this->idProperty} ?? null;
    }

    /**
     * Wraps the given value as a callable or Closure. If the value is a string representing a class name,
     * it instantiates the class. If the value is already a Closure, it is returned as-is. Otherwise,
     * a Closure is returned that resolves to the given value.
     *
     * @param mixed $value The value to wrap as a callable or Closure.
     * @return callable|Closure A callable or Closure representation of the given value.
     */
    protected static function wrapAsCallable($value): callable|Closure
    {

        if (is_string($value) && class_exists($value)) {
            $value = new $value();
        }
        return $value instanceof Closure ? $value : static fn() => $value;
    }

    /**
     * Transforms the provided value into a callable if it is not already.
     * If the value is a string, it attempts to invoke it dynamically.
     *
     * @param mixed $value The value to be checked and potentially transformed.
     * @return mixed The value as a callable or the result of invoking it if it is a string.
     */
    protected static function cast(mixed $value): mixed
    {
        if (!is_callable($value)) {
            //echo 'Is not callable, wrapping as callable!';
            $value = static::wrapAsCallable($value);
        }
        return is_string($value) ? $value(...) : $value;
    }

    /**
     * Retrieves the model data from the parent, filtering out unwanted keys.
     * @return array The filtered model data.
     */
    public static function getModel(): array
    {
        $data = parent::getModel();
        return array_filter($data, static fn($value) => !in_array($value, ['protocol', 'status', 'resolver']), ARRAY_FILTER_USE_KEY);
    }

    public function __invoke(...$args): static
    {
        return $this->handle(...$args);
    }
}