<?php

/**
 * This file is part of Mactronique EDF TeleReleve package.
 *
 * @author Jean-Baptiste Nahan <jbnahan@gmail.com>
 * @copyright 2016 - Jean-Baptiste Nahan
 * @license MIT
 */
namespace Mactronique\TeleReleve\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This command compute the daily consumption.
 */
class CountReleveCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('count')
            ->setDefinition([
                new InputOption('date', null, InputOption::VALUE_REQUIRED, 'The date at you want count and summarize.'),
                new InputOption('send-email', null, InputOption::VALUE_NONE, 'Send by email. If not set throw exception.'),
                new InputOption('yesterdays', null, InputOption::VALUE_NONE, 'Read the data for yesterdays'),
            ])
            ->setDescription('This command compute the daily consumption.')
            ->setHelp("This command can send email and compare the consumption for one days with before day.
    Exemple : %command.full_name% --date 2016-10-05 --yesterdays --send-email

Before try send a email, please, set the SMTP configuration into 'config.yml' file. This is the minimal confituration options :
smtp:
    server: my-smtp-server
    from:
        display_name: Me
        email: my-email@domain.tld
    to:
        display_name: Me
        email: my-email@domain.tld

You can change and custom the email template. The custom template do save into 'src/Templates' folder and is writen in Twig template language.
For set your custom template, set the option 'template' into the 'config.yml' file.
The default type-mime for email is 'text/plain'. If your custom template use the HTML or other mime-type, set the 'mime' option into the 'smtp' section into the 'config.yml' file.
")
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $date = date('Y-m-d');
        if ($input->getOption('date') !== null) {
            $date = $input->getOption('date');
        }

        if ($input->getOption('yesterdays')) {
            $d = \DateTime::createFromFormat('Y-m-d', $date);
            $dayYesterdays = $d->sub(new \DateInterval('P1D'));
            $dateYesterdays = $dayYesterdays->format('Y-m-d');
            $output->writeln('Read count for <info>'.$dateYesterdays.'</info>: ');
            $data = $this->getApplication()->storage()->read($dateYesterdays);
            $nbYesterdays = count($data);
            $output->writeln('Row count : <info>'.$nbYesterdays.'</info>');

            if ($nbYesterdays>0) {
                $table_datasY = $this->computeDayConsumption($data);

                $table = new Table($output);
                $table->setHeaders(['Pricing', 'Start index (Kwh)', 'Last index (Kwh)', 'Delta (Kwh)']);
                $table->setRows($table_datasY[0]);
                $table->render();

                $output->writeln("Total : <info>".$table_datasY[1]."</info> Kwh");
            }
        }

        $output->writeln('Read count for <info>'.$date.'</info>: ');
        $data = $this->getApplication()->storage()->read($date);
        $nb = count($data);
        $output->writeln('Row count : <info>'.$nb.'</info>');

        if ($nb>0) {
            $table_datas = $this->computeDayConsumption($data);

            $table = new Table($output);
            $table->setHeaders(['Pricing', 'Start index (Kwh)', 'Last index (Kwh)', 'Delta (Kwh)']);
            $table->setRows($table_datas[0]);
            $table->render();

            $output->writeln("Total : <info>".$table_datas[1]."</info> Kwh");
        }

        if (isset($table_datasY)) {
            $deltas = [
                [$table_datas[0][0][0], $table_datas[0][0][3], $table_datasY[0][0][3], sprintf('%10s', number_format($table_datas[2] - $table_datasY[2], 3, ',', ' '))],
                [$table_datas[0][1][0], $table_datas[0][1][3], $table_datasY[0][1][3], sprintf('%10s', number_format($table_datas[3] - $table_datasY[3], 3, ',', ' ')),],
                ['Total day', sprintf('%10s', $table_datas[1]), sprintf('%10s', $table_datasY[1]), sprintf('%10s', number_format(($table_datas[2]+$table_datas[3] - ($table_datasY[2]+$table_datasY[3])), 3, ',', ' ')),],
            ];
            $output->writeln("Delta between tow days :");
            $table = new Table($output);
            $table->setHeaders(['Pricing', 'Today (Kwh)', 'Yesterdays (Kwh)', 'Delta (Kwh)']);
            $table->setRows($deltas);
            $table->render();
        }

        if ($input->getOption('send-email')) {
            $datasEmail = [
                'date'=> new \DateTime($date),
                'releve_count' => $nb,
                'periode_debut' => 'Début',
                'periode_fin' => 'Fin',
                'data' => $table_datas[0],
                'conso_total' => $table_datas[1],
            ];
            if (isset($table_datasY)) {
                $datasEmail['data_yesterdays'] = $table_datas[0];
                $datasEmail['conso_total_yesterdays'] = $table_datas[1];
                $datasEmail['dateYesterdays'] = $dayYesterdays;
                $datasEmail['deltas'] = $deltas;
            }

            $this->getApplication()->sendMessage(
                'TeleReleve count for '.$date,
                $datasEmail
            );
            $output->writeln('<comment>E-mail sent !</comment>');
        }

    }

    /**
     * Compute the consumption for one day.
     * @param array $data Data from storage
     *
     * @return array
     */
    private function computeDayConsumption(array $data)
    {
        $table_data = [['', '', '', ''], ['', '', '', '']];
        $first = $data[0];
        $last = end($data);
        $hchc = ($last['hchc'] - $first['hchc'])/1000;
        $hchp = ($last['hchp'] - $first['hchp'])/1000;

        $table_data[0] = [
            'Heures creuses',
            sprintf('%10s', number_format($first['hchc']/1000, 0, ',', ' ')),
            sprintf('%10s', number_format($last['hchc']/1000, 0, ',', ' ')),
            sprintf('%10s', number_format($hchc, 3, ',', ' ')),
        ];
        $table_data[1] = [
            'Heures pleines',
            sprintf('%10s', number_format($first['hchp']/1000, 0, ',', ' ')),
            sprintf('%10s', number_format($last['hchp']/1000, 0, ',', ' ')),
            sprintf('%10s', number_format($hchp, 3, ',', ' ')),
        ];

        $conso_totale = number_format($hchc + $hchp, 3, ',', ' ');

        return [$table_data, $conso_totale, $hchc, $hchp];
    }
}
