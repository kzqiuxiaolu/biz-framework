<?php

namespace Codeages\Biz\Framework\Dao;

use Doctrine\DBAL\Connections\MasterSlaveConnection as DoctrineMasterSlaveConnection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\PingableConnection;
use Throwable;

class MasterSlaveConnection extends DoctrineMasterSlaveConnection
{
    public function update($tableExpression, array $data, array $identifier, array $types = array())
    {
        $this->checkFieldNames(array_keys($data));

        return parent::update($tableExpression, $data, $identifier, $types);
    }

    public function insert($tableExpression, array $data, array $types = array())
    {
        $this->checkFieldNames(array_keys($data));

        return parent::insert($tableExpression, $data, $types);
    }

    public function checkFieldNames($names)
    {
        foreach ($names as $name) {
            if (!ctype_alnum(str_replace('_', '', $name))) {
                throw new \InvalidArgumentException('Field name is invalid.');
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function query()
    {
        $this->connect('master');
        assert($this->_conn instanceof DriverConnection);

        $args = func_get_args();

        $logger = $this->getConfiguration()->getSQLLogger();
        if ($logger) {
            $logger->startQuery($args[0]);
        }

        try {
            $statement = call_user_func_array(array($this->_conn, 'query'), $args);
        } catch (Throwable $ex) {
            throw DBALException::driverExceptionDuringQuery($this->_driver, $ex, $args[0]);
        }

        $statement->setFetchMode($this->defaultFetchMode);

        if ($logger) {
            $logger->stopQuery();
        }

        return $statement;
    }

    public function fetchLockAssoc($statement, array $params = [], array $types = [])
    {
        $this->connect('master');

        return parent::fetchAssoc($statement, $params, $types);
    }


    public function getLock($statement, array $params = array(), array $types = array())
    {
        $this->connect('master');

        $result = parent::fetchAssoc($statement, $params, $types);
        return $result['getLock'];
    }

    public function releaseLock($statement, array $params = array(), array $types = array())
    {
        $this->connect('master');

        $result = parent::fetchAssoc($statement, $params, $types);
        return $result['releaseLock'];
    }

    public function transactional(\Closure $func, \Closure $exceptionFunc = null)
    {
        $this->beginTransaction();
        try {
            $result = $func($this);
            $this->commit();

            return $result;
        } catch (\Exception $e) {
            $this->rollBack();
            !is_null($exceptionFunc) && $exceptionFunc($this);
            throw $e;
        }
    }

    /**
     * Ping the server
     *
     * When the server is not available the method returns FALSE.
     * It is responsibility of the developer to handle this case
     * and abort the request or reconnect manually:
     *
     * @return bool
     *
     * @example
     *
     *   if ($conn->ping() === false) {
     *      $conn->close();
     *      $conn->connect();
     *   }
     *
     * It is undefined if the underlying driver attempts to reconnect
     * or disconnect when the connection is not available anymore
     * as long it returns TRUE when a reconnect succeeded and
     * FALSE when the connection was dropped.
     */
    public function ping()
    {
        $connection = $this->getWrappedConnection();

        if ($connection instanceof PingableConnection) {
            return $connection->ping();
        }

        try {
            $this->slaveQuery($this->getDatabasePlatform()->getDummySelectSQL());

            return true;
        } catch (DBALException $e) {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function slaveQuery()
    {
        assert($this->_conn instanceof DriverConnection);

        $args = func_get_args();

        $logger = $this->getConfiguration()->getSQLLogger();
        if ($logger) {
            $logger->startQuery($args[0]);
        }

        try {
            $statement = call_user_func_array(array($this->_conn, 'query'), $args);
        } catch (Throwable $ex) {
            throw DBALException::driverExceptionDuringQuery($this->_driver, $ex, $args[0]);
        }

        $statement->setFetchMode($this->defaultFetchMode);

        if ($logger) {
            $logger->stopQuery();
        }

        return $statement;
    }
}
