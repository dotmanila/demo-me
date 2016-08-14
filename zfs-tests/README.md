ZFS Testing
###########

Preparation
===========

Building the OS:

::

	bzr branch lp:sysbench
	sudo apt install build-essential libtool libssl-dev
	cd sysbench
	./autogen.sh
	./configure
	make
	sudo make install

SSD RAID1
=========

File IO Tests
-------------

Random r/w test gets about 200MB/s+ with no reads, ARC Caching? 

::

	sysbench --test=fileio --file-num=64 --file-total-size=80G \
		--file-test-mode=rndrw --max-time=1800 --max-requests=0 \
		--num-threads=8 --rand-init=on --file-num=64 --file-fsync-freq=0 \
		--file-block-size=16384 --report-interval=10 run

	[  10s] reads: 2471.20 MB/s writes: 1647.46 MB/s fsyncs: 0.00/s response time: 0.020ms (95%)
	[  20s] reads: 2449.34 MB/s writes: 1632.90 MB/s fsyncs: 0.00/s response time: 0.020ms (95%)
	[  30s] reads: 2530.43 MB/s writes: 1686.95 MB/s fsyncs: 0.00/s response time: 0.019ms (95%)
	[  40s] reads: 2502.60 MB/s writes: 1668.40 MB/s fsyncs: 0.00/s response time: 0.019ms (95%)
	...
	[1790s] reads: 2440.47 MB/s writes: 1626.97 MB/s fsyncs: 0.00/s response time: 0.020ms (95%)
	Operations performed:  288875130 reads, 192583423 writes, 0 Other = 481458553 Total
	Read 4.3046Tb  Written 2.8697Tb  Total transferred 7.1743Tb  (4.0814Gb/sec)
	267476.96 Requests/sec executed

	General statistics:
	    total time:                          1800.0001s
	    total number of events:              481458553
	    total time taken by event execution: 13604.1705s
	    response time:
	         min:                                  0.00ms
	         avg:                                  0.03ms
	         max:                                354.19ms
	         approx.  95 percentile:               0.02ms

	Threads fairness:
	    events (avg/stddev):           60182319.1250/331559.40
	    execution time (avg/stddev):   1700.5213/1.45

	revin@acme:~$ pt-diskstats --devices-regex='sdf1|sdg1' \
		--columns-regex='rd_s|rd_mb_s|rd_rt|wr_s|wr_mb_s|wr_rt|busy|io_s|qtime|s_time'

	  #ts device    rd_s rd_mb_s   rd_rt    wr_s wr_mb_s   wr_rt busy    io_s  qtime
	  0.4 sdf1       0.0     0.0     0.0  2286.2   111.2     0.2  47%  2286.2    0.0
	  0.4 sdg1       0.0     0.0     0.0  2687.6   111.1     0.2  54%  2687.6   -0.0

	  1.0 sdf1       0.0     0.0     0.0  2078.0   114.9     0.2  50%  2078.0   -0.0
	  1.0 sdg1       0.0     0.0     0.0  2305.0   115.0     0.2  50%  2305.0    0.0

	  1.0 sdf1       0.0     0.0     0.0  2194.0   114.0     0.3  56%  2194.0    0.0
	  1.0 sdg1       0.0     0.0     0.0  2216.0   114.2     0.2  50%  2216.0    0.0

	  1.1 sdf1       0.0     0.0     0.0  2915.2   114.5     0.2  55%  2915.2   -0.0
	  1.1 sdg1       0.0     0.0     0.0  2902.1   114.4     0.2  54%  2902.1    0.0

	  0.9 sdf1       0.0     0.0     0.0  2818.5   126.0     0.2  62%  2818.5    0.0
	  0.9 sdg1       0.0     0.0     0.0  2724.2   125.4     0.2  58%  2724.2    0.0

	revin@acme:~$ top
	top - 01:05:19 up  1:01,  2 users,  load average: 14.22, 13.31, 7.74
	Tasks: 623 total,   5 running, 618 sleeping,   0 stopped,   0 zombie
	%Cpu0  :  4.0 us, 92.7 sy,  0.0 ni,  2.7 id,  0.7 wa,  0.0 hi,  0.0 si,  0.0 st
	%Cpu1  :  3.7 us, 91.7 sy,  0.0 ni,  4.7 id,  0.0 wa,  0.0 hi,  0.0 si,  0.0 st
	%Cpu2  :  1.7 us, 91.3 sy,  0.0 ni,  5.0 id,  2.0 wa,  0.0 hi,  0.0 si,  0.0 st
	%Cpu3  :  0.7 us, 91.0 sy,  0.0 ni,  5.3 id,  1.0 wa,  0.0 hi,  2.0 si,  0.0 st
	%Cpu4  :  2.3 us, 92.7 sy,  0.0 ni,  4.0 id,  1.0 wa,  0.0 hi,  0.0 si,  0.0 st
	%Cpu5  :  2.7 us, 95.0 sy,  0.0 ni,  2.3 id,  0.0 wa,  0.0 hi,  0.0 si,  0.0 st
	%Cpu6  :  2.6 us, 89.7 sy,  0.0 ni,  7.6 id,  0.0 wa,  0.0 hi,  0.0 si,  0.0 st
	%Cpu7  :  0.7 us, 93.7 sy,  0.0 ni,  4.0 id,  1.7 wa,  0.0 hi,  0.0 si,  0.0 st
	KiB Mem : 65797152 total, 62290920 free,   567224 used,  2939008 buff/cache
	KiB Swap: 66924540 total, 66924540 free,        0 used. 62985004 avail Mem

Notes
=====

- `--file-extra-flags=direct` does not work on ZFS wen running sysbench