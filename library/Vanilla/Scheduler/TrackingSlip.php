<?php
/**
 * @author Eduardo Garcia Julia <eduardo.garciajulia@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

namespace Vanilla\Scheduler;

use Vanilla\Scheduler\Descriptor\JobDescriptorInterface;
use Vanilla\Scheduler\Driver\DriverSlipInterface;
use Vanilla\Scheduler\Job\JobExecutionStatus;
use Vanilla\Scheduler\Meta\SchedulerJobMeta;

/**
 * Class TrackingSlip
 */
class TrackingSlip implements TrackingSlipInterface {

    /**
     * @var string
     */
    protected $jobInterface;

    /**
     * @var DriverSlipInterface
     */
    protected $driverSlip;

    /**
     * @var JobDescriptorInterface
     */
    protected $jobDescriptor;

    /**
     * @var SchedulerJobMeta
     */
    protected $schedulerJobMeta;

    /**
     * TrackingSlip constructor
     *
     * @param string $jobInterface
     * @param DriverSlipInterface $driverSlip
     * @param JobDescriptorInterface $jobDescriptor
     */
    public function __construct(
        string $jobInterface,
        DriverSlipInterface $driverSlip,
        JobDescriptorInterface $jobDescriptor
    ) {
        $this->jobInterface = $jobInterface;
        $this->driverSlip = $driverSlip;
        $this->jobDescriptor = $jobDescriptor;
        $this->schedulerJobMeta = new SchedulerJobMeta($this);
    }

    /**
     * Get Id
     *
     * @return string
     */
    public function getId(): string {
        $class = $this->jobInterface;
        $type = $this->jobDescriptor->getJobType();
        $id = $this->driverSlip->getId();

        return "{$class}-{$type}-{$id}";
    }

    /**
     * Get the Tracking Id
     * The Id is generated by the TrackingSlip implementation independently of the Job itself.
     *
     * @return string
     */
    public function getTrackingId(): string {
        return uniqid((gethostname() ?: 'unknown')."::", true);
    }

    /**
     * Get Status
     *
     * @return JobExecutionStatus
     */
    public function getStatus(): JobExecutionStatus {
        return $this->driverSlip->getStatus();
    }

    /**
     * Get Driver Slip
     *
     * @return DriverSlipInterface
     */
    public function getDriverSlip(): DriverSlipInterface {
        return $this->driverSlip;
    }

    /**
     * Get JobInterface name
     *
     * @return string
     */
    public function getJobInterface() {
        return $this->jobInterface;
    }

    /**
     * Get Extended Status
     *
     * @return array
     */
    public function getExtendedStatus(): array {
        return $this->driverSlip->getExtendedStatus();
    }

    /**
     * @return JobDescriptorInterface
     */
    public function getDescriptor(): JobDescriptorInterface {
        return $this->jobDescriptor;
    }

    /**
     * @return SchedulerJobMeta
     */
    public function getSchedulerJobMeta(): SchedulerJobMeta {
        return $this->schedulerJobMeta;
    }

    /**
     * Get the Error Message (if exists)
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string {
        return $this->driverSlip->getErrorMessage();
    }
}
