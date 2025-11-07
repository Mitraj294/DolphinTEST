personality testAnalysis(word_info_struct &word_info,
                         const std::string testName,
                         std::ofstream &logOut)
{
    std::fstream file;
    logOut << "Opening test file...\n";
    std::string word;
    file.open("code/test_input.txt");
    std::unordered_map<std::string, std::pair<char, int>>::iterator iter;
    personality persona;
    persona.clear();
    bool con = false;

    if (!file.is_open())
    {
        logOut << "ERROR: Failed to open test with file name: " << testName << "\n\n";
        return persona;
    }

    logOut << "Successfully opened test file.\n";
    while (file >> word)
    {
        if (word[0] == '-')
        {
            persona.name = word.substr(1, word.length() - 1);
            continue;
        }
        if (word[0] == '$')
        {
            con = true;
            continue;
        }
        word = sanitize(word);
        iter = word_info.selfTable.find(word);

        // $$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$
        // $$$$$$$$$$$$$$$$$ Self Weight $$$$$$$$$$$$$$$$$$
        // $$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$
        if (!con)
        {
            if (word.empty())
            {
                continue;
            }
            if (iter != word_info.selfTable.end())
            {
                switch (iter->second.first)
                {
                case 'A':
                    if (persona.aSWords.find(word) != persona.aSWords.end())
                    {
                        dupeWord(word, logOut);
                    }
                    else
                    {
                        persona.aSWords.insert(word);
                        persona.aSelf += iter->second.second; // Add word weight
                        persona.aSelfCount += 1;
                    }
                    if (word_info.adjustTable.find(word) != word_info.adjustTable.end())
                    {
                        persona.aAdjWords.insert(word);
                        persona.aAdj += iter->second.second;
                        persona.aAdjCount += 1;
                    }
                    else
                    {
                        persona.aTask += iter->second.second;
                    }

                    break;

                case 'B':
                    if (persona.bSWords.find(word) != persona.bSWords.end())
                    {
                        dupeWord(word, logOut);
                    }
                    else
                    {
                        persona.bSWords.insert(word);
                        persona.bSelf += iter->second.second;
                        persona.bSelfCount += 1;
                    }
                    if (word_info.adjustTable.find(word) != word_info.adjustTable.end())
                    {
                        persona.bAdjWords.insert(word);
                        persona.bAdj += iter->second.second;
                        persona.bAdjCount += 1;
                    }
                    else
                    {
                        persona.bTask += iter->second.second;
                    }
                    break;

                case 'C':
                    if (persona.cSWords.find(word) != persona.cSWords.end())
                    {
                        dupeWord(word, logOut);
                    }
                    else
                    {
                        persona.cSWords.insert(word);
                        persona.cSelf += iter->second.second;
                        persona.cSelfCount += 1;
                    }
                    if (word_info.adjustTable.find(word) != word_info.adjustTable.end())
                    {
                        persona.cAdjWords.insert(word);
                        persona.cAdj += iter->second.second;
                        persona.cAdjCount += 1;
                    }
                    else
                    {
                        persona.cTask += iter->second.second;
                    }
                    break;

                case 'D':
                    if (persona.dSWords.find(word) != persona.dSWords.end())
                    {
                        dupeWord(word, logOut);
                    }
                    else
                    {
                        persona.dSWords.insert(word);
                        persona.dSelf += iter->second.second;
                        persona.dSelfCount += 1;
                    }
                    if (word_info.adjustTable.find(word) != word_info.adjustTable.end())
                    {
                        persona.dAdjWords.insert(word);
                        persona.dAdj += iter->second.second;
                        persona.dAdjCount += 1;
                    }
                    else
                    {
                        persona.dTask += iter->second.second;
                    }

                    break;

                default:
                    break;
                }
            }
            else
            {
                logOut << "\n@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@";
                logOut << "\n@@@ Caution: word (" << word << ") exists in testFile.txt but not in selfWeight.txt";
                logOut << "\n@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@\n";
            }
        }

        // $$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$
        // $$$$$$$$$$$$$$$$ Concept Weight $$$$$$$$$$$$$$$$
        // $$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$

        if (con)
        {
            if (word.empty())
            {
                continue;
            }
            if (iter != word_info.concTable.end())
            {
                switch (iter->second.first)
                {
                case 'A':
                    if (persona.aCWords.find(word) != persona.aCWords.end())
                    {
                        dupeWord(word, logOut);
                    }
                    else
                    {
                        persona.aCWords.insert(word);
                        persona.aCon += iter->second.second; // Add word weight
                        persona.aConCount += 1;
                    }

                    break;
                case 'B':
                    if (persona.bCWords.find(word) != persona.bCWords.end())
                    {
                        dupeWord(word, logOut);
                    }
                    else
                    {
                        persona.bCWords.insert(word);
                        persona.bCon += iter->second.second;
                        persona.bConCount += 1;
                    }

                    break;
                case 'C':
                    if (persona.cCWords.find(word) != persona.cCWords.end())
                    {
                        dupeWord(word, logOut);
                    }
                    else
                    {
                        persona.cCWords.insert(word);
                        persona.cCon += iter->second.second;
                        persona.cConCount += 1;
                    }

                    break;
                case 'D':
                    if (persona.dCWords.find(word) != persona.dCWords.end())
                    {
                        dupeWord(word, logOut);
                    }
                    else
                    {
                        persona.dCWords.insert(word);
                        persona.dCon += iter->second.second;
                        persona.dConCount += 1;
                    }

                    break;

                default:
                    break;
                }
            }
            else
            {
                logOut << "\n@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@";
                logOut << "\n@@@ Caution: word (" << word << ") exists in testFile.txt but not in conceptWeight.txt";
                logOut << "\n@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@\n";
            }
        }
    }

    file.close();
    logOut << "Finished digesting and closed test file.\n\n";
    return persona;
};

personality test_repeat(word_info_struct &word_info,
                        const std::string testName,
                        std::fstream &repeat_in,
                        std::ofstream &logOut)
{
    std::fstream file;
    logOut << "Opening test file...\n";
    std::string word;
    std::string line;
    std::string sub_line;
    file.open(testName);
    std::unordered_map<std::string, std::pair<char, int>>::iterator iter;
    personality persona;
    persona.clear();
    bool con = false;

    if (!file.is_open())
    {
        logOut << "ERROR: Failed to open test with file name: " << testName << "\n\n";
        return persona;
    }

    logOut << "Successfully opened test file.\n";

    while (getline(repeat_in, line))
    {
        sub_line = line.substr(0, 17);
        if (sub_line == "Self Total Words:")
        {
            persona.old_self_count = std::stoi(line.substr(18, line.length()));
        }
        if (line == "******* Self Projection *******")
        {
            getline(repeat_in, line);
            getline(repeat_in, line);
            for (int i = 0; i < 5; i++)
            {
                persona.RI.push_back(line);
                getline(repeat_in, line);
                getline(repeat_in, line);
                // std::cout << line << std::endl;
            }
            persona.RI.push_back(line);
        }
    }
    while (file >> word)
    {
        if (word[0] == '-')
        {
            persona.name = word.substr(1, word.length() - 1);
            while ((file >> word) && !con)
            {
                if (word[0] == '$')
                {
                    con = true;
                    continue;
                }
            }
            continue;
        }
        word = sanitize(word);
        iter = word_info.selfTable.find(word);

        // $$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$
        // $$$$$$$$$$$$$$$$ Concept Weight $$$$$$$$$$$$$$$$
        // $$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$
        if (word.empty())
        {
            continue;
        }
        if (iter != word_info.concTable.end())
        {
            switch (iter->second.first)
            {
            case 'A':
                if (persona.aCWords.find(word) != persona.aCWords.end())
                {
                    dupeWord(word, logOut);
                }
                else
                {
                    persona.aCWords.insert(word);
                    persona.aCon += iter->second.second; // Add word weight
                    persona.aConCount += 1;
                }

                break;
            case 'B':
                if (persona.bCWords.find(word) != persona.bCWords.end())
                {
                    dupeWord(word, logOut);
                }
                else
                {
                    persona.bCWords.insert(word);
                    persona.bCon += iter->second.second;
                    persona.bConCount += 1;
                }

                break;
            case 'C':
                if (persona.cCWords.find(word) != persona.cCWords.end())
                {
                    dupeWord(word, logOut);
                }
                else
                {
                    persona.cCWords.insert(word);
                    persona.cCon += iter->second.second;
                    persona.cConCount += 1;
                }

                break;
            case 'D':
                if (persona.dCWords.find(word) != persona.dCWords.end())
                {
                    dupeWord(word, logOut);
                }
                else
                {
                    persona.dCWords.insert(word);
                    persona.dCon += iter->second.second;
                    persona.dConCount += 1;
                }

                break;

            default:
                break;
            }
        }
        else
        {
            logOut << "\n@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@";
            logOut << "\n@@@ Caution: word (" << word << ") exists in testFile.txt but not in conceptWeight.txt";
            logOut << "\n@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@\n";
        }
    }

    file.close();
    logOut << "Finished digesting and closed test file.\n\n";
    return persona;
};

void process_results(personality &persona, word_info_struct &word_info, results &r)
{
    // Self Weight:
    float aTot = (float(persona.aSelf) / float(word_info.ceiling.a_self_max));
    float bTot = (float(persona.bSelf) / float(word_info.ceiling.b_self_max));
    float cTot = (float(persona.cSelf) / float(word_info.ceiling.c_self_max));
    float dTot = (float(persona.dSelf) / float(word_info.ceiling.d_self_max));

    // Adjusted Self:
    float aAdjTot = (float(persona.aAdj) / float(word_info.ceiling.a_adj_max));
    float bAdjTot = (float(persona.bAdj) / float(word_info.ceiling.b_adj_max));
    float cAdjTot = (float(persona.cAdj) / float(word_info.ceiling.c_adj_max));
    float dAdjTot = (float(persona.dAdj) / float(word_info.ceiling.d_adj_max));

    // Social Self:
    float aTaskTot = (float(persona.aTask) / (float(word_info.ceiling.a_task_max)));
    float bTaskTot = (float(persona.bTask) / (float(word_info.ceiling.b_task_max)));
    float cTaskTot = (float(persona.cTask) / (float(word_info.ceiling.c_task_max)));
    float dTaskTot = (float(persona.dTask) / (float(word_info.ceiling.d_task_max)));

    // Meta data:
    int self_total_count = persona.aSelfCount + persona.bSelfCount + persona.cSelfCount + persona.dSelfCount;
    int con_total_count = persona.aConCount + persona.bConCount + persona.cConCount + persona.dConCount;
    int adj_total_count = persona.aAdjCount + persona.bAdjCount + persona.cAdjCount + persona.dAdjCount;
    float avg = (aTot + bTot + cTot + dTot) / 4;
    float self_avg = avg;

    // Decision Approach:
    float dA = (aAdjTot + bAdjTot + cAdjTot + dAdjTot) / 4;
    float dT = (aTaskTot + bTaskTot + cTaskTot + dTaskTot) / 4;
    float distFromAvg = float(dT - dA);
    float dec_approach = avg;

    if (distFromAvg < 0)
    {
        dec_approach = avg - std::abs(distFromAvg) * (avg);
    } // Left of average
    else if (distFromAvg > 0)
    {
        dec_approach = avg + std::abs(distFromAvg) * (avg - 1.0);
    } // Right of average
    else
    {
        dec_approach = 0;
    } // On Average

    // Concept Weight:
    float a_con_tot = (float(persona.aCon) / float(word_info.ceiling.a_con_max));
    float b_con_tot = (float(persona.bCon) / float(word_info.ceiling.b_con_max));
    float c_con_tot = (float(persona.cCon) / float(word_info.ceiling.c_con_max));
    float d_con_tot = (float(persona.dCon) / float(word_info.ceiling.d_con_max));
    avg = (a_con_tot + b_con_tot + c_con_tot + d_con_tot) / 4;

    // Store Reults
    r.email = persona.name;
    r.self_total_words = self_total_count;
    r.conc_total_words = con_total_count;
    r.adj_total_words = adj_total_count;
    r.self_a_score = aTot;
    r.self_b_score = bTot;
    r.self_c_score = cTot;
    r.self_d_score = dTot;
    r.self_avg = self_avg;
    r.dec_approach = dec_approach;
    r.conc_a_score = a_con_tot;
    r.conc_b_score = b_con_tot;
    r.conc_c_score = c_con_tot;
    r.conc_d_score = d_con_tot;
    r.conc_avg = avg;
}
